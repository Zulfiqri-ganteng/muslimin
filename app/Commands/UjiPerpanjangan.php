<?php

namespace App\Commands;

use App\Libraries\PklAjuan;
use App\Libraries\PklForm;
use App\Libraries\PklHak;
use App\Libraries\SuratBerkas;
use App\Libraries\SuratJenis;
use App\Libraries\SuratKeputusan;
use App\Libraries\SuratPeriksa;
use App\Libraries\SuratPerpanjangan as Lib;
use App\Libraries\SuratPkl;
use App\Libraries\SuratSekolah;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * Uji Surat Permohonan Perpanjangan PKL (docs/DESAIN-PERPANJANGAN-PKL.md, aturan R1–R12): rumus tanggal & jendela, hak, buat /
 * ikut surat / maksimal satu kali / ditarik, tanggal efektif (ACC, batal ACC, batal surat), Status Siswa + Excel, berkas Word,
 * halaman staf, dan API.
 *
 * Data uji bertanda "ZZUJI PJ" (nama pembuat), NIS "ZZUJIPJ…", IP "10.9.7.8"; dibuat & dihapus sendiri. Pengaturan PKL dan tabel
 * peran dipulihkan di akhir. Nomor surat diuji di tahun 2032 supaya tidak menyentuh nomor sungguhan.
 *
 * Jalankan:  php spark dev:uji-perpanjangan
 */
class UjiPerpanjangan extends BaseCommand
{
    use UjiPeranAman;

    protected $group       = 'dev';
    protected $name        = 'dev:uji-perpanjangan';
    protected $description = 'Uji Surat Perpanjangan PKL: aturan tanggal, maksimal 1×, tanggal efektif, Word, halaman staf, API.';

    private const NIS  = 'ZZUJIPJ';
    private const IP   = '10.9.7.8';
    private const OLEH = 'ZZUJI PJ';

    private int $lulus = 0;
    private int $gagal = 0;
    private BaseConnection $db;
    private ?array $pengaturanAsli = null;
    private int $kelasId = 0;

    private function cek(string $judul, bool $ok, string $detail = ''): void
    {
        $ok ? $this->lulus++ : $this->gagal++;
        CLI::write(($ok ? '  [OK]    ' : '  [GAGAL] ') . $judul . ($detail !== '' ? '  → ' . $detail : ''), $ok ? 'green' : 'red');
    }

    private function bagian(string $judul): void
    {
        CLI::newLine();
        CLI::write('== ' . $judul . ' ==', 'yellow');
    }

    public function run(array $params)
    {
        try {
            $this->db = db_connect();
            if (! Lib::tabelAda()) {
                CLI::error('Tabel pkl_perpanjangan belum ada — jalankan php spark migrate dulu.');

                return EXIT_ERROR;
            }
            $this->bersihkan();
            $this->simpanPeranAsli();
            $this->pengaturanAsli = (new PklPengaturanModel())->ambil();
            unset($this->pengaturanAsli['id']);
            $this->setelPeranBawaan();
            $this->atur(['hak_peran' => null, 'surat_perlu_acc' => null, 'selesai_paling_akhir' => null, 'durasi_maks_hari' => 0,
                'perpanjang_buka' => 1, 'perpanjang_hari_sebelum' => 7, 'perpanjang_hari_sesudah' => 30,
                'kepsek_nama' => 'Kepsek Uji, S.T.', 'waka_hubin_nama' => 'Hubin Uji, S.Pd.', 'kontak_surat_nama' => 'Kontak Uji', 'kontak_surat_hp' => '0812000']);

            $this->ujiMurni();
            $this->ujiHak();
            // Kelas XII: dilayani Status Siswa (tingkat PKL) dan Layanan Siswa (tingkat PKL + XII) — kelas X tidak.
            $k = $this->db->table('kelas')->select('id')->where('deleted_at', null)->where('tingkat', 'XII')->orderBy('id')->get()->getRowArray()
                ?? $this->db->table('kelas')->select('id')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
            if ($k === null) {
                $this->cek('ada kelas sebagai bahan uji DB', false, 'tabel kelas kosong');
            } else {
                $this->kelasId = (int) $k['id'];
                $this->ujiAlur();
                $this->ujiTanpaTanggal();
                $this->ujiBerkas();
                $this->ujiHalaman();
                $this->ujiApi();
                $this->ujiLayanan();
                $this->ujiApiLengkap();
            }
        } catch (\Throwable $e) {
            $this->cek('FATAL ' . get_class($e), false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
        $this->bersihkan();

        CLI::newLine();
        CLI::write(sprintf('HASIL: %d lulus, %d gagal', $this->lulus, $this->gagal), $this->gagal === 0 ? 'green' : 'red');

        return $this->gagal === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // =================================================================
    // 1. Murni
    // =================================================================

    private function ujiMurni(): void
    {
        $this->bagian('Rumus tanggal, jendela, alasan, isian');
        $this->cek('lama: 30 Okt → 30 Nov = "1 (satu) bulan"', Lib::lamaTeks('2026-10-30', '2026-11-30') === '1 (satu) bulan', Lib::lamaTeks('2026-10-30', '2026-11-30'));
        $this->cek('lama: +14 hari = "14 (empat belas) hari"', Lib::lamaTeks('2026-10-30', '2026-11-13') === '14 (empat belas) hari', Lib::lamaTeks('2026-10-30', '2026-11-13'));
        $this->cek('lama: 30 Okt → 30 Jan = "3 (tiga) bulan"; 31 Des → 28 Feb = "2 (dua) bulan"; 45 hari = "45 (empat puluh lima) hari"',
            Lib::lamaTeks('2026-10-30', '2027-01-30') === '3 (tiga) bulan' && Lib::lamaTeks('2026-12-31', '2027-02-28') === '2 (dua) bulan'
            && Lib::lamaTeks('2026-10-01', '2026-11-15') === '45 (empat puluh lima) hari');
        $this->cek('lama: tanggal mundur / tak sah = ""', Lib::lamaTeks('2026-10-30', '2026-10-30') === '' && Lib::lamaTeks('x', '2026-10-30') === '');
        $this->cek('mulai perpanjangan = sehari sesudah selesai semula (lintas bulan & tahun)', Lib::mulai('2026-10-31') === '2026-11-01' && Lib::mulai('2026-12-31') === '2027-01-01');

        $p = ['perpanjang_hari_sebelum' => 7, 'perpanjang_hari_sesudah' => 30];
        $this->cek('jendela H-7: 22 Okt untuk selesai 30 Okt = "dini", 23 Okt = "buka"', Lib::jendela('2026-10-30', $p, '2026-10-22')['status'] === 'dini' && Lib::jendela('2026-10-30', $p, '2026-10-23')['status'] === 'buka');
        $this->cek('jendela H+30: 29 Nov = "buka", 30 Nov = "lewat"; buka_mulai/tutup benar', Lib::jendela('2026-10-30', $p, '2026-11-29')['status'] === 'buka'
            && Lib::jendela('2026-10-30', $p, '2026-11-30')['status'] === 'lewat' && Lib::jendela('2026-10-30', $p)['buka_mulai'] === '2026-10-23' && Lib::jendela('2026-10-30', $p)['tutup'] === '2026-11-29');
        $this->cek('jendela 0 = tanpa batas (sebelum & sesudah)', Lib::jendela('2026-10-30', ['perpanjang_hari_sebelum' => 0, 'perpanjang_hari_sesudah' => 0], '2025-01-01')['status'] === 'buka'
            && Lib::jendela('2026-10-30', ['perpanjang_hari_sebelum' => 0, 'perpanjang_hari_sesudah' => 0], '2030-01-01')['status'] === 'buka');
        $this->cek('setelan bawaan bila kolom kosong: buka, 7, 30', Lib::setelan([]) === ['buka' => true, 'sebelum' => 7, 'sesudah' => 30]);

        $this->cek('alasan pilihan → kalimat; ketikan dirapikan ("Karena Perusahaan butuh." → "perusahaan butuh")', Lib::alasan(['alasan_pilih' => '0']) === Lib::ALASAN[0][0]
            && Lib::alasan(['alasan_pilih' => 'lain', 'alasan_lain' => 'Karena Perusahaan butuh.']) === 'perusahaan butuh' && Lib::indeksAlasan(Lib::ALASAN[2][0]) === '2' && Lib::indeksAlasan('apa saja') === 'lain');
        $h = Lib::periksa(['tanggal_surat' => '', 'selesai_baru' => '', 'alasan_pilih' => 'lain', 'alasan_lain' => '']);
        $this->cek('periksa: tanggal surat, tanggal baru, alasan kosong → galat per kolom', ! $h['ok'] && isset($h['galat']['tanggal_surat'], $h['galat']['selesai_baru'], $h['galat']['alasan']));
        $a = Lib::aturanTanggal('2026-08-01', '2026-10-30', 'sekolah', '2026-10-30', []);
        $b = Lib::aturanTanggal('2026-08-01', '2026-10-30', 'sekolah', '2027-06-30', []);
        $c = Lib::aturanTanggal('2026-11-01', '2026-10-30', 'staf', '2026-11-30', []);
        $this->cek('aturan: tanggal baru = semula ditolak; > 183 hari ditolak; semula < mulai PKL ditolak', isset($a['galat']['selesai_baru'], $b['galat']['selesai_baru'], $c['galat']['selesai_lama']));
        $w = Lib::aturanTanggal('2026-08-01', '2026-10-30', 'siswa', '2026-12-31', ['selesai_paling_akhir' => '2026-12-15', 'durasi_maks_hari' => 120]);
        $this->cek('peringatan (tidak memblokir): diketik siswa, lewat batas sekolah, total PKL > maksimal', $w['galat'] === [] && count($w['peringatan']) === 3, implode(' | ', $w['peringatan']));
        $this->cek('kunci: ajuan + tanggal baru; lanjutan beda; tanggal beda beda', Lib::kunci(5, '2026-11-30') === Lib::kunci(5, '2026-11-30') && Lib::kunci(5, '2026-11-30') !== Lib::kunci(5, '2026-11-30', 2) && Lib::kunci(5, '2026-11-30') !== Lib::kunci(5, '2026-12-01'));
        $t = Lib::token(['tanggal_surat' => '2026-10-26'], ['sapaan' => '', 'selesai_lama' => '2026-10-30', 'selesai_baru' => '2026-11-30', 'alasan_perpanjangan' => 'x', 'alamat' => 'Jl. A']);
        $this->cek('penanda surat: semula, mulai, baru, lama, periode, alamat, sapaan bawaan', $t['selesai_lama'] === '30 Oktober 2026' && $t['mulai_perpanjangan'] === '31 Oktober 2026'
            && $t['selesai_baru'] === '30 November 2026' && $t['lama_perpanjangan'] === '1 (satu) bulan' && $t['periode_perpanjangan'] === '31 Oktober s.d. 30 November 2026'
            && $t['alamat_lengkap'] === 'Jl. A' && $t['sapaan'] === 'Pimpinan' && $t['tanggal_surat'] === '26 Oktober 2026', json_encode($t, JSON_UNESCAPED_UNICODE));
        $this->cek('jenis terdaftar: bernomor, wajib ACC, siap, template ada, urutan sesudah Balasan sebelum Penarikan', SuratJenis::bernomor(SuratJenis::PERPANJANGAN) && SuratJenis::perluAcc(SuratJenis::PERPANJANGAN, [])
            && SuratJenis::siap(SuratJenis::PERPANJANGAN) && SuratJenis::templateLengkap(SuratJenis::PERPANJANGAN)
            && array_search(SuratJenis::PERPANJANGAN, SuratJenis::kode(), true) === array_search(SuratJenis::BALASAN, SuratJenis::kode(), true) + 1
            && array_search(SuratJenis::PENARIKAN, SuratJenis::kode(), true) === array_search(SuratJenis::PERPANJANGAN, SuratJenis::kode(), true) + 1);
    }

    // =================================================================
    // 2. Hak
    // =================================================================

    private function ujiHak(): void
    {
        $this->bagian('Hak & menu');
        $this->cek('bawaan: Operator Buat + Unduh, Waka Hubin ACC (aturan umum surat bernomor)', PklHak::bolehSurat('operator', 'buat', SuratJenis::PERPANJANGAN) && PklHak::bolehSurat('operator', 'unduh', SuratJenis::PERPANJANGAN)
            && PklHak::bolehSurat('hubin', 'acc', SuratJenis::PERPANJANGAN) && ! PklHak::bolehSurat('hubin', 'buat', SuratJenis::PERPANJANGAN));
        $this->cek('alamat admin/surat/perpanjangan(/1/ubah) menuntut hak Buat jenis ini', PklHak::hakUntukAlamat('admin/surat/perpanjangan') === 'surat_buat:perpanjangan_pkl'
            && PklHak::hakUntukAlamat('admin/surat/perpanjangan/12/ubah') === 'surat_buat:perpanjangan_pkl');
        $menu = json_encode(\App\Libraries\MenuAdmin::sidebar(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->cek('menu samping: "Surat Perpanjangan PKL" aktif + "Sertifikat PKL" bertanda Segera', str_contains($menu, '"admin/surat/perpanjangan","Surat Perpanjangan PKL","file-lines",false,false')
            && str_contains($menu, '"admin/pkl/sertifikat","Sertifikat PKL","award",false,true'), mb_substr($menu, 0, 0));
    }

    // =================================================================
    // 3. Alur: buat, ikut, maksimal 1×, ditarik, tanggal efektif
    // =================================================================

    /** @var list<int> */
    private array $s = [];
    private int $ajuan = 0;

    private function ujiAlur(): void
    {
        $this->bagian('Buat, ikut surat, maksimal satu kali, ditarik, tanggal efektif');
        $lib = new Lib();
        $p   = (new PklPengaturanModel())->ambil();
        for ($i = 1; $i <= 4; $i++) {
            $this->s[$i] = $this->buatSiswa($i);
        }
        $hariIni = date('Y-m-d');
        $lama    = Lib::tambahHari($hariIni, -1); // PKL sudah selesai kemarin
        $baru    = Lib::tambahHari($hariIni, 30);
        $this->ajuan = $this->buatAjuan($this->s[1], 'ZZUJIPJ Perusahaan Satu', [$this->s[2], $this->s[3], $this->s[4]], Lib::tambahHari($hariIni, -60), $lama);
        $this->cek('bahan: ajuan disetujui 4 siswa, selesai kemarin', $this->ajuan > 0 && count((new SuratPkl())->ajuan([$this->ajuan])[0]['siswa'] ?? []) === 4);

        $form = $lib->ajuanForm($this->ajuan);
        $this->cek('ajuanForm: tanggal semula dari sekolah, 4 siswa bisa dipilih', $form !== null && $form['selesai_lama'] === $lama && $form['sumber_lama'] === 'sekolah' && $form['bisa'] === 4);
        $cari = $lib->cari('ZZUJIPJ Perusahaan');
        $this->cek('cari: nama perusahaan, nama siswa, kode ajuan', in_array($this->ajuan, array_column($cari, 'id'), true)
            && in_array($this->ajuan, array_column($lib->cari(self::OLEH . ' 3'), 'id'), true) && in_array($this->ajuan, array_column($lib->cari(PklPengajuanModel::kode($this->ajuan)), 'id'), true) && $lib->cari('Z') === []);

        $in = static fn (array $siswa, string $baru, array $tambah = []): array => $tambah + ['ajuan_id' => 0, 'siswa' => $siswa, 'tanggal_surat' => date('Y-m-d'), 'sapaan' => 'HRD',
            'selesai_baru' => $baru, 'alasan_pilih' => '0'];
        $r1 = $lib->buat($in([$this->s[1]], $baru, ['ajuan_id' => $this->ajuan]), $this->ctx('operator'), $p);
        $id = (int) ($r1['surat_id'] ?? 0);
        $sr = $id > 0 ? $this->db->table('surat_sekolah')->where('id', $id)->get()->getRowArray() : [];
        $pp = $id > 0 ? $this->db->table('pkl_perpanjangan')->where('surat_id', $id)->get()->getRowArray() : [];
        $this->cek('buat (staf): surat baru menunggu ACC, jenis perpanjangan, 1 siswa, baris pkl_perpanjangan tanggal benar', $r1['ok'] && $r1['kode'] === 'baru' && ($sr['status'] ?? '') === 'menunggu'
            && ($sr['jenis'] ?? '') === SuratJenis::PERPANJANGAN && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $id]) === 1
            && ($pp['selesai_lama'] ?? '') === $lama && ($pp['selesai_baru'] ?? '') === $baru && ($pp['sumber_lama'] ?? '') === 'sekolah', json_encode($r1, JSON_UNESCAPED_UNICODE));
        $this->cek('jejak: riwayat surat "buat" + riwayat ajuan menyebut Surat Perpanjangan', $this->jumlah('surat_sekolah_riwayat', ['surat_id' => $id, 'aksi' => 'buat']) === 1
            && (int) $this->db->table('pkl_riwayat')->where('pengajuan_id', $this->ajuan)->like('catatan', 'Surat Perpanjangan')->countAllResults() === 1);

        $r2 = $lib->buat($in([$this->s[1]], Lib::tambahHari($baru, 5), ['ajuan_id' => $this->ajuan]), $this->ctx('operator'), $p);
        $this->cek('maksimal SATU KALI: siswa yang sama ditolak (kode "sudah"), surat tidak bertambah', ! $r2['ok'] && $r2['kode'] === 'sudah' && str_contains($r2['pesan'], 'satu kali')
            && $this->jumlah('surat_sekolah', ['pengajuan_id' => $this->ajuan, 'jenis' => SuratJenis::PERPANJANGAN]) === 1, $r2['pesan']);

        $r3 = $lib->buat($in([$this->s[2]], $baru, ['ajuan_id' => $this->ajuan]), $this->ctx('operator'), $p);
        $this->cek('teman tanggal baru SAMA → ikut surat yang sama (2 siswa, judul "(2 siswa)")', $r3['ok'] && $r3['kode'] === 'ikut' && (int) $r3['surat_id'] === $id
            && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $id]) === 2 && str_ends_with((string) $this->db->table('surat_sekolah')->select('judul')->where('id', $id)->get()->getRow('judul'), '(2 siswa)'), json_encode($r3, JSON_UNESCAPED_UNICODE));

        $baruLain = Lib::tambahHari($hariIni, 14);
        $r4 = $lib->buat($in([$this->s[3]], $baruLain, ['ajuan_id' => $this->ajuan]), $this->ctx('operator'), $p);
        $this->cek('teman tanggal baru BEDA → surat terpisah', $r4['ok'] && $r4['kode'] === 'baru' && (int) $r4['surat_id'] !== $id);

        // Penarikan siswa 4 → perpanjangan ditolak (R1).
        $hp = SuratPkl::periksa(SuratJenis::PENARIKAN, ['tanggal_surat' => date('Y-m-d'), 'tgl_efektif' => date('Y-m-d'), 'alasan_pilih' => '0']);
        $tr = (new SuratPkl())->buat(SuratJenis::PENARIKAN, $hp, [$this->ajuan], [$this->s[4]], $this->ctx('operator'), $p);
        $r5 = $lib->buat($in([$this->s[4]], $baru, ['ajuan_id' => $this->ajuan]), $this->ctx('operator'), $p);
        $this->cek('siswa dengan Surat Penarikan aktif → ditolak', $tr['ok'] && ! $r5['ok'] && str_contains($r5['pesan'], 'ditarik'), $r5['pesan']);
        $this->cek('ajuanForm: siswa 1–3 terkunci (sudah diperpanjang), siswa 4 terkunci (ditarik), bisa = 0', ($f = $lib->ajuanForm($this->ajuan)) !== null && $f['bisa'] === 0 && $f['siswa'][3]['ditarik'] !== null);
        $this->cek('siswa bukan anggota ajuan → ditolak', ! $lib->buat($in([999999999], $baru, ['ajuan_id' => $this->ajuan]), $this->ctx('operator'), $p)['ok']);

        // Tanggal efektif (R8).
        $ef = $lib->selesaiEfektif([$this->s[1], $this->s[2]]);
        $this->cek('sebelum ACC: tanggal efektif = semula, menunggu = true', ($ef[$this->s[1]]['selesai'] ?? '') === $lama && ($ef[$this->s[1]]['menunggu'] ?? false) === true && ($ef[$this->s[1]]['diperpanjang'] ?? true) === false);
        $fase = fn (): string => (string) ((new PklPengajuanModel())->statusSiswa(['X', 'XI', 'XII'], 0, self::OLEH . ' 1', '', 10, 1)[0][0]['fase'] ?? '?');
        $this->cek('Status Siswa sebelum ACC: "selesai" (tanggal semula sudah lewat)', $fase() === 'selesai', $fase());
        $kep = new SuratKeputusan();
        $acc = $kep->putuskan($id, 'acc', [], $this->ctx('hubin'), $p);
        $ef  = $lib->selesaiEfektif([$this->s[1], $this->s[2]]);
        $this->cek('sesudah ACC: tanggal efektif = tanggal baru (kedua siswa), diperpanjang = true', $acc['ok'] && ($ef[$this->s[1]]['selesai'] ?? '') === $baru && ($ef[$this->s[2]]['selesai'] ?? '') === $baru && $ef[$this->s[1]]['diperpanjang'], json_encode($acc, JSON_UNESCAPED_UNICODE));
        $this->cek('pkl_pengajuan.tanggal_selesai TIDAK ditimpa (R9)', (string) $this->db->table('pkl_pengajuan')->select('tanggal_selesai')->where('id', $this->ajuan)->get()->getRow('tanggal_selesai') === $lama);
        $this->cek('Status Siswa sesudah ACC: "sedang" + selesai_efektif = tanggal baru', $fase() === 'sedang'
            && (string) ((new PklPengajuanModel())->statusSiswa(['X', 'XI', 'XII'], 0, self::OLEH . ' 1', '', 10, 1)[0][0]['selesai_efektif'] ?? '') === $baru, $fase());
        $rows = (new PklPengajuanModel())->statusSiswa(['X', 'XI', 'XII'], 0, self::OLEH, '', 50, 1)[0];
        $ws   = \App\Libraries\PklStatusExcel::buat($rows, ['belum' => 0], [], ['school_name' => 'Sekolah Uji', 'academic_year' => '2031/2032'], ['XI'], [])->getActiveSheet();
        $kol  = [];
        for ($b = 1; $b <= 40; $b++) {
            $kol[(string) $ws->getCell('B' . $b)->getValue()] = (string) $ws->getCell('L' . $b)->getValue();
        }
        $this->cek('Excel Status Siswa: kolom L "Diperpanjang s.d." terisi untuk siswa yang sudah berlaku saja', in_array('Diperpanjang s.d.', $kol, true)
            && ($kol[self::OLEH . ' 1'] ?? '') === \App\Libraries\IsianBantu::tanggalIndo($baru) && ($kol[self::OLEH . ' 3'] ?? 'x') === '', json_encode(array_filter($kol), JSON_UNESCAPED_UNICODE));
        $this->cek('berakhir ≤ 7 hari: siswa yang sudah punya perpanjangan/penarikan tidak muncul', ! in_array($this->s[1], array_column($lib->berakhir(7, Lib::tambahHari($hariIni, -3)), 'siswa_id'), true));

        $batalAcc = $kep->putuskan($id, 'batal_acc', ['catatan' => 'uji batal ACC perpanjangan'], $this->ctx('hubin'), $p);
        $this->cek('batal ACC → tanggal efektif kembali ke semula', $batalAcc['ok'] && ($lib->selesaiEfektif([$this->s[1]])[$this->s[1]]['selesai'] ?? '') === $lama, json_encode($batalAcc, JSON_UNESCAPED_UNICODE));
        $batal = $kep->putuskan($id, 'batal', ['catatan' => 'uji batalkan surat perpanjangan'], $this->ctx('operator'), $p);
        $this->cek('surat dibatalkan → perpanjangan tidak aktif lagi, siswa boleh meminta lagi', $batal['ok'] && $lib->aktif([$this->s[1]], $this->ajuan) === []
            && ($ulang = $lib->buat($in([$this->s[1]], $baru, ['ajuan_id' => $this->ajuan]), $this->ctx('operator'), $p))['ok'] && $ulang['kode'] === 'baru', json_encode($batal, JSON_UNESCAPED_UNICODE));
        $this->idUlang = (int) ($ulang['surat_id'] ?? 0);

        // Ubah data surat (belum bernomor).
        $m  = (new SuratSekolah())->muat($this->idUlang);
        $u  = $lib->ubahSurat($m, $in([$this->s[1]], Lib::tambahHari($baru, 7), ['ajuan_id' => $this->ajuan, 'alasan_pilih' => 'lain', 'alasan_lain' => 'Proyek perusahaan diperpanjang']), $this->ctx('operator'), $p);
        $pp = $this->db->table('pkl_perpanjangan')->where('surat_id', $this->idUlang)->get()->getRowArray();
        $isi = SuratSekolah::dekodeIsi((string) $this->db->table('surat_sekolah')->select('isi')->where('id', $this->idUlang)->get()->getRow('isi'));
        $this->cek('ubah surat: tanggal baru & alasan berubah di surat DAN di pkl_perpanjangan; kunci ikut tanggal baru', $u['ok'] && ($pp['selesai_baru'] ?? '') === Lib::tambahHari($baru, 7)
            && ($isi['alasan_perpanjangan'] ?? '') === 'proyek perusahaan diperpanjang' && ($isi['kunci'] ?? '') === Lib::kunci($this->ajuan, Lib::tambahHari($baru, 7)), json_encode($u, JSON_UNESCAPED_UNICODE));
        $this->cek('ubah surat: menambah siswa yang sudah punya surat perpanjangan lain → ditolak', ! $lib->ubahSurat((new SuratSekolah())->muat($this->idUlang), $in([$this->s[1], $this->s[3]], Lib::tambahHari($baru, 7), ['ajuan_id' => $this->ajuan]), $this->ctx('operator'), $p)['ok']);
        $periksa = SuratPeriksa::untuk($this->db->table('surat_sekolah')->where('id', $this->idUlang)->get()->getRowArray(), (new SuratSekolah())->siswa($this->idUlang), $p);
        $this->cek('SuratPeriksa: surat lengkap → tanpa peringatan bahaya', ! SuratPeriksa::adaBahaya($periksa), json_encode($periksa, JSON_UNESCAPED_UNICODE));
    }

    private int $idUlang = 0;

    // =================================================================
    // 4. Tanggal semula belum tercatat + jendela siswa
    // =================================================================

    private function ujiTanpaTanggal(): void
    {
        $this->bagian('Tanggal semula belum tercatat (diketik) + jendela form siswa');
        $lib = new Lib();
        $p   = (new PklPengaturanModel())->ambil();
        $s5  = $this->buatSiswa(5);
        $aj  = $this->buatAjuan($s5, 'ZZUJIPJ Perusahaan Dua', [], null, null);
        $f   = $lib->ajuanForm($aj);
        $this->cek('ajuanForm: belum tercatat → selesai_lama null, sumber null', $f !== null && $f['selesai_lama'] === null && $f['sumber_lama'] === null);
        $in = ['ajuan_id' => $aj, 'siswa' => [$s5], 'tanggal_surat' => date('Y-m-d'), 'selesai_baru' => '2026-12-31', 'alasan_pilih' => '1'];
        $r  = $lib->buat($in, $this->ctx('operator'), $p);
        $this->cek('tanpa tanggal semula → galat selesai_lama', ! $r['ok'] && isset($r['galat']['selesai_lama']), $r['pesan']);
        $hariIni = '2026-11-02';
        $r = $lib->buat($in + ['selesai_lama' => '2026-10-20'], ['oleh' => 'Siswa: ' . self::OLEH . ' 5', 'admin_id' => null, 'peran' => null, 'ip' => self::IP], $p, ['sumber' => 'siswa', 'jendela' => true, 'hariIni' => '2026-10-10']);
        $this->cek('jendela siswa: terlalu dini → ditolak dengan tanggal mulai bisa diminta', ! $r['ok'] && str_contains($r['pesan'], 'bisa diminta mulai 13 Oktober 2026'), $r['pesan']);
        $r = $lib->buat($in + ['selesai_lama' => '2026-10-20'], ['oleh' => 'Siswa: ' . self::OLEH . ' 5', 'admin_id' => null, 'peran' => null, 'ip' => self::IP], $p, ['sumber' => 'siswa', 'jendela' => true, 'hariIni' => '2026-11-20']);
        $this->cek('jendela siswa: lewat H+30 → ditolak "hubungi operator"', ! $r['ok'] && str_contains($r['pesan'], 'sudah lewat'), $r['pesan']);
        $r = $lib->buat($in + ['selesai_lama' => '2026-10-20'], ['oleh' => 'Siswa: ' . self::OLEH . ' 5', 'admin_id' => null, 'peran' => null, 'ip' => self::IP], $p, ['sumber' => 'siswa', 'jendela' => true, 'hariIni' => $hariIni]);
        $pp = $this->db->table('pkl_perpanjangan')->where('surat_id', (int) ($r['surat_id'] ?? 0))->get()->getRowArray();
        $sr = $this->db->table('surat_sekolah')->where('id', (int) ($r['surat_id'] ?? 0))->get()->getRowArray();
        $this->cek('siswa SESUDAH tanggal selesai (masih dalam H+30) → diterima; asal tanggal "siswa"; surat sumber "siswa"; ada peringatan', $r['ok'] && ($pp['sumber_lama'] ?? '') === 'siswa' && ($pp['selesai_lama'] ?? '') === '2026-10-20'
            && (! SuratSekolah::kolomSumber() || ($sr['sumber'] ?? '') === 'siswa') && ($r['peringatan'] ?? []) !== [], json_encode($r, JSON_UNESCAPED_UNICODE));
        $per = SuratPeriksa::untuk($sr, (new SuratSekolah())->siswa((int) $r['surat_id']), $p);
        $this->cek('SuratPeriksa: tanggal diketik siswa → "awas" (dilewati ACC massal)', in_array('awas', array_column($per, 'tingkat'), true) && str_contains(implode(' ', array_column($per, 'teks')), 'diketik siswa'));
        $ef = $lib->selesaiEfektif([$s5])[$s5] ?? [];
        $this->cek('selesaiEfektif: ajuan tanpa tanggal → selesai null, perpanjangan menunggu ACC', array_key_exists('selesai', $ef) && $ef['selesai'] === null && $ef['menunggu'] === true, json_encode($ef));
    }

    // =================================================================
    // 5. Berkas Word
    // =================================================================

    private function ujiBerkas(): void
    {
        $this->bagian('Berkas Word');
        $id = $this->idUlang;
        if ($id <= 0) {
            $this->cek('ada surat bahan uji Word', false);

            return;
        }
        $this->db->table('surat_sekolah')->where('id', $id)->update(['tanggal_surat' => '2032-11-02']); // nomor uji di tahun 2032
        $kep = new SuratKeputusan();
        $kep->putuskan($id, 'acc', [], $this->ctx('hubin'), (new PklPengaturanModel())->ambil());
        $h = (new SuratBerkas())->bangun([$id], $this->ctx('operator'));
        $this->cek('unduh: 1 berkas .docx bernama "{urut} Surat Perpanjangan PKL - …"', $h['ok'] === true && str_ends_with($h['nama'], '.docx') && str_contains($h['nama'], 'Surat Perpanjangan PKL - ZZUJIPJ Perusahaan Satu'), $h['ok'] ? $h['nama'] : json_encode($h));
        if (! $h['ok']) {
            return;
        }
        $teks = $this->isiDocx($h['biner']);
        $isi  = SuratSekolah::dekodeIsi((string) $this->db->table('surat_sekolah')->select('isi')->where('id', $id)->get()->getRow('isi'));
        $baru = (string) $isi['selesai_baru'];
        $lama = (string) $isi['selesai_lama'];
        $this->cek('isi: perihal, kalimat pembuka dengan tanggal semula, periode perpanjangan, alasan, sapaan', str_contains($teks, 'Permohonan Perpanjangan Waktu PKL')
            && str_contains($teks, 'yang berakhir pada tanggal ' . \App\Libraries\IsianBantu::tanggalIndo($lama))
            && str_contains($teks, Lib::lamaTeks($lama, $baru) . ', ' . SuratPkl::periodeTeks(Lib::mulai($lama), $baru))
            && str_contains($teks, 'karena proyek perusahaan diperpanjang.') && str_contains($teks, 'Bapak/Ibu HRD'));
        $this->cek('isi: siswa, Kepala Sekolah, Waka Hubin, kaki ACC, tanpa penanda ${…} tersisa', str_contains($teks, self::OLEH . ' 1') && str_contains($teks, 'Kepsek Uji, S.T.') && str_contains($teks, 'Hubin Uji, S.Pd.')
            && str_contains($teks, 'disetujui secara elektronik') && ! str_contains($teks, '${'));
        $this->cek('bagian surat yang sama dengan Surat Balasan tetap ada (Lokasi Penempatan, Alamat, NB)', str_contains($teks, 'Lokasi Penempatan') && str_contains($teks, 'NB :') && str_contains($teks, 'Jl. Uji Perpanjangan No. 1'));
    }

    // =================================================================
    // 6. Halaman staf (controller) & 7. API
    // =================================================================

    private function ujiHalaman(): void
    {
        $this->bagian('Halaman staf');
        $html = (string) $this->panggil(\App\Controllers\Admin\SuratPerpanjangan::class, 'operator', 'index');
        $this->cek('halaman pilih: kotak cari + daftar "PKL berakhir dalam 7 hari"', str_contains($html, 'name="q"') && str_contains($html, 'PKL berakhir dalam 7 hari'));
        $html = (string) $this->panggil(\App\Controllers\Admin\SuratPerpanjangan::class, 'operator', 'index', ['q' => 'ZZUJIPJ Perusahaan']);
        $this->cek('halaman cari: hasil memuat ajuan uji', str_contains($html, 'ZZUJIPJ Perusahaan Satu') && str_contains($html, 'ZZUJIPJ Perusahaan Dua'));
        $s6 = $this->buatSiswa(6);
        $aj = $this->buatAjuan($s6, 'ZZUJIPJ Perusahaan Tiga', [], Lib::tambahHari(date('Y-m-d'), -30), Lib::tambahHari(date('Y-m-d'), 3));
        $html = (string) $this->panggil(\App\Controllers\Admin\SuratPerpanjangan::class, 'operator', 'index', ['ajuan' => $aj]);
        $this->cek('halaman formulir: siswa dicentang, tanggal semula terkunci, tombol cepat, SATU tombol simpan (Operator tanpa ACC)', str_contains($html, 'name="siswa[]" value="' . $s6 . '" class="cek" checked')
            && str_contains($html, 'dari data sekolah') && str_contains($html, '+1 bulan') && substr_count($html, 'type="submit"') === 1 && str_contains($html, 'Buat surat &amp; ajukan untuk ACC'));
        $this->cek('Beranda: siswa yang berakhir ≤ 7 hari terhitung', in_array($s6, array_column((new Lib())->berakhir(7), 'siswa_id'), true));
        $r = $this->panggil(\App\Controllers\Admin\SuratPerpanjangan::class, 'operator', 'simpan', [], ['ajuan_id' => (string) $aj, 'siswa' => [(string) $s6], 'tanggal_surat' => date('Y-m-d'),
            'sapaan' => 'Pimpinan', 'selesai_baru' => Lib::tambahHari(date('Y-m-d'), 33), 'alasan_pilih' => '0']);
        $id = (int) ($this->db->table('surat_sekolah')->select('id')->where('pengajuan_id', $aj)->where('jenis', SuratJenis::PERPANJANGAN)->get()->getRow('id') ?? 0);
        $this->cek('simpan lewat halaman: surat dibuat, dialihkan ke detail surat', $id > 0 && $r instanceof \CodeIgniter\HTTP\RedirectResponse && str_contains($r->getHeaderLine('Location'), 'admin/surat/' . $id));
        $html = $this->teks($this->panggil(\App\Controllers\Admin\Surat::class, 'operator', 'detail', [], [], $id));
        $this->cek('detail surat: baris "Perpanjangan … Berlaku setelah ACC" + tombol Ubah data', str_contains($html, 'Berlaku setelah ACC') && str_contains($html, 'perpanjangan/' . $id . '/ubah'), mb_substr(strip_tags($html), 0, 200));
        $html = $this->teks($this->panggil(\App\Controllers\Admin\SuratPerpanjangan::class, 'operator', 'ubah', [], [], $id));
        preg_match('~name="siswa\[\]"[^>]*>~', $html, $cb);
        $this->cek('halaman ubah: siswa surat ini tetap bisa dipilih + tombol "Simpan perubahan"', str_contains($html, 'value="' . $s6 . '" class="cek" checked') && str_contains($html, 'Simpan perubahan'), ($cb[0] ?? '(tanpa centang)') . ' | ' . (str_contains($html, 'Simpan perubahan') ? 'ada tombol' : 'tanpa tombol'));
        $html = (string) $this->panggil(\App\Controllers\Admin\Pkl::class, 'operator', 'detail', [], [], $aj);
        $this->cek('detail ajuan: lencana perpanjangan menunggu ACC + tombol "Buat Surat Perpanjangan"', str_contains($html, 'Perpanjangan menunggu ACC s.d.') && str_contains($html, 'Buat Surat Perpanjangan'));
        $this->cek('hubin (tanpa hak Buat) ditolak di halaman Perpanjangan', $this->panggil(\App\Controllers\Admin\SuratPerpanjangan::class, 'hubin', 'index') instanceof \CodeIgniter\HTTP\RedirectResponse);
    }

    private function ujiApi(): void
    {
        $this->bagian('API surat (staf)');
        $s7 = $this->buatSiswa(7);
        $aj = $this->buatAjuan($s7, 'ZZUJIPJ Perusahaan Empat', [], Lib::tambahHari(date('Y-m-d'), -30), Lib::tambahHari(date('Y-m-d'), 2));
        [$k, $j] = $this->apiSurat('operator', 'form', ['ajuan_id' => $aj], [], 'perpanjangan');
        $this->cek('GET surat/form/perpanjangan?ajuan_id= → 200, ajuan + alasan_saran + setelan', $k === 200 && (int) ($j['data']['ajuan']['id'] ?? 0) === $aj && count($j['data']['alasan_saran'] ?? []) === 4 && isset($j['data']['setelan']['sebelum']), (string) $k);
        $body = ['ajuan_id' => $aj, 'siswa' => [$s7], 'tanggal_surat' => date('Y-m-d'), 'selesai_baru' => Lib::tambahHari(date('Y-m-d'), 40), 'alasan_pilih' => '2'];
        [$k, $j] = $this->apiSurat('operator', 'buat', [], $body, 'perpanjangan');
        $this->cek('POST surat/perpanjangan → 201, dibuat 1', $k === 201 && count($j['data']['dibuat'] ?? []) === 1, (string) $k . ' ' . ($j['message'] ?? ''));
        [$k, $j] = $this->apiSurat('operator', 'buat', [], $body, 'perpanjangan');
        $this->cek('POST kedua siswa yang sama → 409 kode "sudah" (maksimal satu kali)', $k === 409 && ($j['data']['kode'] ?? '') === 'sudah', (string) $k);
        [$k, $j] = $this->apiSurat('operator', 'buat', [], ['ajuan_id' => $aj, 'siswa' => [$s7]], 'perpanjangan');
        $this->cek('POST tanpa tanggal & alasan → 422 dengan galat per kolom', $k === 422 && isset($j['data']['errors']['selesai_baru']), (string) $k);
        [$k] = $this->apiSurat('hubin', 'buat', [], $body, 'perpanjangan');
        $this->cek('Waka Hubin (tanpa hak Buat) → 403', $k === 403, (string) $k);
    }

    // =================================================================
    // 8. Layanan Siswa (form publik, F3)
    // =================================================================

    private function ujiLayanan(): void
    {
        $this->bagian('Layanan Siswa: ikon, formulir publik, teman, status, jendela');
        $L   = new \App\Libraries\LayananSiswa();
        $p   = (new PklPengaturanModel())->ambil();
        $hi  = date('Y-m-d');
        $pub = \App\Libraries\LayananPublik::semua((new \App\Models\SettingModel())->get());
        $this->cek('ikon publik "Ajukan surat perpanjangan PKL": layanan/surat/perpanjangan, bagian Surat dari sekolah, status buka', isset($pub['surat_perpanjangan'])
            && str_ends_with($pub['surat_perpanjangan']['url'], 'layanan/surat/perpanjangan') && ($pub['surat_perpanjangan']['bagian']['siswa'] ?? '') === 'Surat dari sekolah'
            && $pub['surat_perpanjangan']['status'] === \App\Libraries\LayananPublik::BUKA && is_file(FCPATH . 'assets/img/ilustrasi/surat-perpanjangan.svg'));
        $this->cek('Layanan Siswa: kunci "perpanjangan" terdaftar & siap, tujuan masuk sah', isset(\App\Libraries\LayananSiswa::SURAT['perpanjangan']) && in_array('perpanjangan', \App\Libraries\LayananSiswa::SIAP, true)
            && \App\Libraries\LayananSiswa::tujuanSah('perpanjangan') === 'perpanjangan');

        $s8 = $this->buatSiswa(8);
        $s9 = $this->buatSiswa(9);
        $aj = $this->buatAjuan($s8, 'ZZUJIPJ Perusahaan Lima', [$s9], Lib::tambahHari($hi, -60), Lib::tambahHari($hi, 3));
        $pr = $L->profil($s8);
        $this->cek('kelayakan: ajuan disetujui + form buka → boleh', $L->kelayakan($pr, $p)['perpanjangan']['boleh'] === true);
        $f = $L->formPerpanjangan($pr, $p);
        $this->cek('formulir: tanggal semula, jendela buka, 4 pilihan cepat (+2 minggu, +1/2/3 bulan), teman bisa dicentang', $f['lama'] === Lib::tambahHari($hi, 3) && $f['jendela']['status'] === 'buka'
            && array_column($f['cepat'], 'label') === ['+2 minggu', '+1 bulan', '+2 bulan', '+3 bulan'] && $f['cepat'][0]['tanggal'] === Lib::tambahHari($hi, 17)
            && count($f['teman']) === 1 && $f['teman'][0]['bisa'] === true, json_encode($f['cepat']));

        session()->set(\App\Libraries\LayananSiswa::SESI, ['id' => $s8, 't' => time()]);
        $html = $this->teks($this->panggil(\App\Controllers\Layanan::class, 'operator', 'surat', [], [], 'perpanjangan'));
        $this->cek('halaman publik: 4 langkah, pilihan cepat bertanggal, centang teman, tombol "Kirim permintaan", tanpa skrip sebaris', str_contains($html, 'Kenapa diperpanjang?')
            && str_contains($html, '+1 bulan</b> — sampai ' . \App\Libraries\IsianBantu::tanggalIndo($f['cepat'][1]['tanggal'])) && str_contains($html, 'name="teman[]" value="' . $s9 . '"')
            && str_contains($html, 'Kirim permintaan') && preg_match('~<script(?![^>]*src=)[^>]*>~', $html) !== 1, mb_substr(strip_tags($html), 0, 120));

        $r = $L->ajukanPerpanjangan($s8, ['pilih_tanggal' => $f['cepat'][1]['tanggal'], 'alasan_pilih' => '0', 'teman' => [(string) $s9]], self::IP, $p);
        $sr = $this->db->table('surat_sekolah')->where('id', (int) ($r['surat_id'] ?? 0))->get()->getRowArray() ?? [];
        $this->cek('kirim: surat menunggu ACC, 2 siswa (diri + teman), asal "siswa", riwayat "minta"', $r['ok'] && ($sr['status'] ?? '') === 'menunggu'
            && $this->jumlah('surat_sekolah_siswa', ['surat_id' => (int) $sr['id']]) === 2 && (! SuratSekolah::kolomSumber() || $sr['sumber'] === 'siswa')
            && $this->jumlah('surat_sekolah_riwayat', ['surat_id' => (int) $sr['id'], 'aksi' => 'minta']) === 1, json_encode($r, JSON_UNESCAPED_UNICODE));
        $mSaya  = array_values(array_filter($L->permintaan($s8), static fn (array $x) => $x['jenis'] === SuratJenis::PERPANJANGAN));
        $mTeman = array_values(array_filter($L->permintaan($s9), static fn (array $x) => $x['jenis'] === SuratJenis::PERPANJANGAN));
        $this->cek('status: pengaju "Menunggu ACC"; teman ikut tercatat + catatan "Namamu dicantumkan oleh temanmu"', count($mSaya) === 1 && $mSaya[0]['status_teks'] === 'Menunggu ACC'
            && count($mTeman) === 1 && str_contains($mTeman[0]['catatan'], 'Namamu dicantumkan oleh temanmu, ' . self::OLEH . ' 8'), json_encode($mTeman, JSON_UNESCAPED_UNICODE));
        $kotak = (new SuratSekolah())->ringkasPermintaanUntuk('operator', $p);
        $this->cek('kotak "Permintaan dari siswa" (Daftar Surat): jenis perpanjangan ikut, menunggu ≥ 1', ($kotak[SuratJenis::PERPANJANGAN]['menunggu'] ?? 0) >= 1);
        $r2 = $L->ajukanPerpanjangan($s9, ['pilih_tanggal' => 'lain', 'selesai_baru' => Lib::tambahHari($hi, 50), 'alasan_pilih' => '1'], self::IP, $p);
        $this->cek('teman minta lagi sendiri → ditolak (maksimal satu kali)', ! $r2['ok'] && $r2['kode'] === 'sudah', $r2['pesan']);
        $html = $this->teks($this->panggil(\App\Controllers\Layanan::class, 'operator', 'surat', [], [], 'perpanjangan'));
        $this->cek('halaman publik sesudah kirim: "sudah punya Surat Perpanjangan … hanya boleh satu kali", tanpa formulir', str_contains($html, 'Kamu sudah punya Surat Perpanjangan PKL') && ! str_contains($html, 'Kirim permintaan'));

        // Terlalu dini (H-7) & tanggal semula belum tercatat.
        $s10 = $this->buatSiswa(10);
        $this->buatAjuan($s10, 'ZZUJIPJ Perusahaan Enam', [], Lib::tambahHari($hi, -30), Lib::tambahHari($hi, 20));
        session()->set(\App\Libraries\LayananSiswa::SESI, ['id' => $s10, 't' => time()]);
        $html = $this->teks($this->panggil(\App\Controllers\Layanan::class, 'operator', 'surat', [], [], 'perpanjangan'));
        $this->cek('PKL selesai 20 hari lagi → info "bisa diminta mulai {H-7}", tanpa formulir', str_contains($html, 'bisa diminta mulai <b>' . \App\Libraries\IsianBantu::tanggalIndo(Lib::tambahHari($hi, 13)))
            && ! str_contains($html, 'Kirim permintaan'));
        $s11 = $this->buatSiswa(11);
        $this->buatAjuan($s11, 'ZZUJIPJ Perusahaan Tujuh', [], null, null);
        session()->set(\App\Libraries\LayananSiswa::SESI, ['id' => $s11, 't' => time()]);
        $html = $this->teks($this->panggil(\App\Controllers\Layanan::class, 'operator', 'surat', [], [], 'perpanjangan'));
        $this->cek('tanggal semula belum tercatat → isian "PKL-mu selesai tanggal" + tanggal baru wajib (tanpa pilihan cepat)', str_contains($html, 'name="selesai_lama"') && ! str_contains($html, '+1 bulan</b>'));
        $r3 = $L->ajukanPerpanjangan($s11, ['pilih_tanggal' => 'lain', 'selesai_lama' => Lib::tambahHari($hi, -2), 'selesai_baru' => Lib::tambahHari($hi, 28), 'alasan_pilih' => 'lain', 'alasan_lain' => 'Perusahaan masih butuh'], self::IP, $p);
        $pp = $this->db->table('pkl_perpanjangan')->where('surat_id', (int) ($r3['surat_id'] ?? 0))->get()->getRowArray() ?? [];
        $this->cek('kirim dengan tanggal semula diketik (sesudah PKL selesai) → diterima, asal tanggal "siswa"', $r3['ok'] && ($pp['sumber_lama'] ?? '') === 'siswa', $r3['pesan']);

        $this->atur(['perpanjang_buka' => 0]);
        $p0 = (new PklPengaturanModel())->ambil();
        $this->cek('form ditutup sekolah → kelayakan "sedang ditutup", ikon publik bertanda tutup', ! $L->kelayakan($L->profil($s10), $p0)['perpanjangan']['boleh']
            && str_contains($L->kelayakan($L->profil($s10), $p0)['perpanjangan']['alasan'], 'ditutup')
            && \App\Libraries\LayananPublik::semua((new \App\Models\SettingModel())->get())['surat_perpanjangan']['status'] === \App\Libraries\LayananPublik::TUTUP);
        $this->atur(['perpanjang_buka' => 1]);
        session()->remove(\App\Libraries\LayananSiswa::SESI);
    }

    // =================================================================
    // 9. API lengkap (F4): staf + publik Layanan Siswa (token)
    // =================================================================

    /** @var array<string, array<string, mixed>> respons nyata (data fiktif ZZUJI PJ) untuk fixture kontrak Flutter */
    private array $fx = [];

    private function ujiApiLengkap(): void
    {
        $this->bagian('API staf (pkl/…, surat/…) + API publik Layanan Siswa (token)');
        $hi = date('Y-m-d');
        $s12 = $this->buatSiswa(12);
        $s13 = $this->buatSiswa(13);
        $aj  = $this->buatAjuan($s12, 'ZZUJIPJ Perusahaan Delapan', [$s13], Lib::tambahHari($hi, -60), Lib::tambahHari($hi, 4));

        // --- staf
        [$k, $j] = $this->apiPkl('operator', 'ringkasan', []);
        $this->cek('GET pkl/ringkasan (Operator): perpanjangan.berakhir_7 & menunggu berupa angka', $k === 200 && is_int($j['data']['perpanjangan']['berakhir_7'] ?? null) && is_int($j['data']['perpanjangan']['menunggu'] ?? null), (string) $k);
        $this->fx['pkl_ringkasan_perpanjangan'] = ['perpanjangan' => $j['data']['perpanjangan'] ?? null];
        [, $jh] = $this->apiPkl('hubin', 'ringkasan', []);
        $this->cek('GET pkl/ringkasan (Waka Hubin, tanpa hak Buat): perpanjangan = null', array_key_exists('perpanjangan', $jh['data'] ?? []) && $jh['data']['perpanjangan'] === null);
        [$k, $j] = $this->apiPkl('operator', 'berakhir', ['hari' => '7']);
        $this->cek('GET pkl/berakhir?hari=7 → siswa uji ikut (selesai 4 hari lagi, sisa = 4)', $k === 200 && in_array($s12, array_column($j['data'] ?? [], 'siswa_id'), true)
            && (int) (array_values(array_filter($j['data'], static fn (array $x) => $x['siswa_id'] === $s12))[0]['sisa'] ?? -1) === 4, (string) $k);
        // Fixture HANYA data fiktif (daftar ini memuat semua siswa di database — siswa asli tidak boleh ikut ke repo aplikasi).
        $this->fx['pkl_berakhir'] = ['data' => array_values(array_filter($j['data'] ?? [], static fn (array $x): bool => str_starts_with((string) $x['nama'], self::OLEH)))] + $j;
        [$k] = $this->apiPkl('hubin', 'berakhir', []);
        $this->cek('GET pkl/berakhir oleh Waka Hubin → 403', $k === 403, (string) $k);

        [$k, $j] = $this->apiSurat('operator', 'form', ['ajuan_id' => $aj], [], 'perpanjangan');
        $this->fx['surat_form_perpanjangan'] = $j;
        [$k, $j] = $this->apiSurat('operator', 'buat', [], ['ajuan_id' => $aj, 'siswa' => [$s12], 'tanggal_surat' => $hi, 'selesai_baru' => Lib::tambahHari($hi, 34), 'alasan_pilih' => '0', 'sapaan' => 'HRD'], 'perpanjangan');
        $sid = (int) ($j['data']['dibuat'][0]['id'] ?? 0);
        $this->fx['surat_buat_perpanjangan'] = $j;
        [$k, $j] = $this->apiSurat('operator', 'buat', [], ['ajuan_id' => $aj, 'siswa' => [$s12], 'tanggal_surat' => $hi, 'selesai_baru' => Lib::tambahHari($hi, 40), 'alasan_pilih' => '0'], 'perpanjangan');
        $this->fx['galat_409_perpanjangan_sudah'] = $j;
        [$k, $j] = $this->apiSurat('operator', 'detail', [], [], $sid);
        $this->cek('GET surat/{id}: blok perpanjangan (semula, baru, lama_teks, periode_teks, berlaku=false)', $k === 200 && ($j['data']['perpanjangan']['selesai_baru'] ?? '') === Lib::tambahHari($hi, 34)
            && ($j['data']['perpanjangan']['berlaku'] ?? true) === false && ($j['data']['perpanjangan']['lama_teks'] ?? '') !== '' && ($j['data']['perpanjangan']['periode_teks'] ?? '') !== '', (string) $k);
        $this->fx['surat_detail_perpanjangan'] = $j;
        [$k, $j] = $this->apiSurat('operator', 'ubah', [], ['ajuan_id' => $aj, 'siswa' => [$s12, $s13], 'tanggal_surat' => $hi, 'selesai_baru' => Lib::tambahHari($hi, 35), 'alasan_pilih' => '2', 'sapaan' => 'HRD'], $sid);
        $this->cek('POST surat/{id}/ubah (perpanjangan): 2 siswa + tanggal baru tersimpan', $k === 200 && $this->jumlah('surat_sekolah_siswa', ['surat_id' => $sid]) === 2
            && (string) $this->db->table('pkl_perpanjangan')->select('selesai_baru')->where('surat_id', $sid)->get()->getRow('selesai_baru') === Lib::tambahHari($hi, 35), (string) $k . ' ' . ($j['message'] ?? ''));
        [$k, $j] = $this->apiSurat('operator', 'ubah', [], ['ajuan_id' => $aj, 'siswa' => [$s12], 'tanggal_surat' => $hi, 'selesai_baru' => Lib::tambahHari($hi, 2), 'alasan_pilih' => '2'], $sid);
        $this->cek('POST surat/{id}/ubah tanggal baru ≤ semula → 422 galat selesai_baru', $k === 422 && isset($j['data']['errors']['selesai_baru']), (string) $k);
        $this->fx['galat_422_perpanjangan'] = $j;
        (new SuratKeputusan())->putuskan($sid, 'acc', [], $this->ctx('hubin'), (new PklPengaturanModel())->ambil());
        [$k, $j] = $this->apiPkl('operator', 'detail', [], $aj);
        $ang = array_column($j['data']['anggota'] ?? [], null, 'siswa_id');
        $this->cek('GET pkl/ajuan/{id}: anggota[].perpanjangan + selesai_efektif (tanggal baru sesudah ACC) + hak.buat_perpanjangan', $k === 200
            && ($ang[$s12]['perpanjangan']['berlaku'] ?? false) === true && ($ang[$s12]['selesai_efektif'] ?? '') === Lib::tambahHari($hi, 35)
            && ($j['data']['hak']['buat_perpanjangan'] ?? null) === true, (string) $k);
        $this->fx['pkl_ajuan_detail_perpanjangan'] = ['anggota' => array_values(array_map(static fn (array $a) => array_intersect_key($a, array_flip(['siswa_id', 'nama', 'perpanjangan', 'selesai_efektif'])), $ang)), 'hak' => ['buat_perpanjangan' => $j['data']['hak']['buat_perpanjangan'] ?? null]];
        [$k, $j] = $this->apiPkl('operator', 'siswa', ['q' => self::OLEH . ' 12']);
        $row = $j['data'][0] ?? [];
        $this->cek('GET pkl/siswa: tanggal_selesai, selesai_efektif, diperpanjang = true, fase "sedang"', $k === 200 && ($row['diperpanjang'] ?? false) === true && ($row['selesai_efektif'] ?? '') === Lib::tambahHari($hi, 35) && ($row['fase'] ?? '') === 'sedang', json_encode($row));
        $this->fx['pkl_siswa_perpanjangan'] = ['data' => [$row]];

        // --- publik Layanan Siswa
        $s14 = $this->buatSiswa(14);
        $s15 = $this->buatSiswa(15);
        $this->buatAjuan($s14, 'ZZUJIPJ Perusahaan Sembilan', [$s15], Lib::tambahHari($hi, -60), Lib::tambahHari($hi, 2));
        [$k, $j] = $this->apiLayanan('kelas', [], []);
        $this->cek('GET publik/layanan/kelas → daftar per tingkat', $k === 200 && is_array($j['data'][0]['kelas'] ?? null), (string) $k);
        [$k, $j] = $this->apiLayanan('masuk', [], ['siswa_id' => $s14, 'tanggal_lahir' => '2001-01-01']);
        $this->cek('POST publik/layanan/masuk bukti salah → 422 (tanpa token)', $k === 422 && ! isset($j['data']['token']), (string) $k);
        $this->fx['layanan_galat_422_masuk'] = $j;
        [$k, $j] = $this->apiLayanan('masuk', [], ['siswa_id' => $s14, 'tanggal_lahir' => '2009-05-17']);
        $token = (string) ($j['data']['token'] ?? '');
        $this->cek('POST publik/layanan/masuk benar → token + berlaku_sampai + profil (tanpa NISN/tanggal lahir)', $k === 200 && $token !== '' && ! empty($j['data']['berlaku_sampai'])
            && ($j['data']['siswa']['id'] ?? 0) === $s14 && ! isset($j['data']['siswa']['nisn']) && ($j['data']['siswa']['ajuan']['status'] ?? '') === 'disetujui', (string) $k);
        $this->fx['layanan_masuk'] = $j;
        [$k] = $this->apiLayanan('saya', [], []);
        $this->cek('tanpa token → 401 "masuk lagi"', $k === 401, (string) $k);
        [$k] = $this->apiLayanan('saya', [], [], substr($token, 0, -2) . 'xx');
        $this->cek('token diubah (tanda tangan tak cocok) → 401', $k === 401, (string) $k);
        $this->cek('token kedaluwarsa (sesudah ' . (\App\Libraries\LayananSiswa::TTL / 60) . ' menit) → tidak sah', \App\Libraries\LayananToken::baca($token, time() + \App\Libraries\LayananSiswa::TTL + 5) === null && \App\Libraries\LayananToken::baca($token) === $s14);
        [$k, $j] = $this->apiLayanan('saya', [], [], $token);
        $sp = array_values(array_filter($j['data']['surat'] ?? [], static fn (array $x) => $x['kunci'] === 'perpanjangan'))[0] ?? [];
        $this->cek('GET publik/layanan/saya → surat "perpanjangan" boleh + api = true; permintaan = []', $k === 200 && ($sp['boleh'] ?? false) === true && ($sp['api'] ?? false) === true && ($j['data']['permintaan'] ?? null) === [], (string) $k);
        $this->fx['layanan_saya'] = $j;
        [$k, $j] = $this->apiLayanan('perpanjangan', [], [], $token);
        $this->cek('GET publik/layanan/perpanjangan → status "isi", 4 pilihan cepat bertanggal, teman bisa dicentang, alasan 4', $k === 200 && ($j['data']['status'] ?? '') === 'isi'
            && count($j['data']['cepat'] ?? []) === 4 && ($j['data']['cepat'][1]['teks'] ?? '') !== '' && ($j['data']['teman'][0]['bisa'] ?? false) === true && count($j['data']['alasan_saran'] ?? []) === 4, (string) $k);
        $this->fx['layanan_perpanjangan_form'] = $j;
        $cepat = (string) ($j['data']['cepat'][1]['tanggal'] ?? '');
        [$k, $j] = $this->apiLayanan('ajukanPerpanjangan', [], ['pilih_tanggal' => 'lain', 'alasan_pilih' => '0'], $token);
        $this->cek('POST publik/layanan/perpanjangan "tanggal lain" kosong → 422 galat selesai_baru', $k === 422 && isset($j['data']['errors']['selesai_baru']), (string) $k);
        $this->fx['layanan_galat_422_perpanjangan'] = $j;
        [$k, $j] = $this->apiLayanan('ajukanPerpanjangan', [], ['pilih_tanggal' => $cepat, 'alasan_pilih' => '1', 'teman' => [$s15]], $token);
        $this->cek('POST publik/layanan/perpanjangan → 201, surat menunggu ACC berisi diri + teman', $k === 201 && $this->jumlah('surat_sekolah_siswa', ['surat_id' => (int) ($j['data']['surat_id'] ?? 0)]) === 2, (string) $k . ' ' . ($j['message'] ?? ''));
        $this->fx['layanan_perpanjangan_kirim'] = $j;
        [$k, $j] = $this->apiLayanan('ajukanPerpanjangan', [], ['pilih_tanggal' => $cepat, 'alasan_pilih' => '1'], $token);
        $this->cek('POST kedua → 409 kode "sudah"', $k === 409 && ($j['data']['kode'] ?? '') === 'sudah', (string) $k);
        [$k, $j] = $this->apiLayanan('perpanjangan', [], [], $token);
        $this->cek('GET formulir sesudah kirim → status "sudah" + kalimat "hanya boleh satu kali"', $k === 200 && ($j['data']['status'] ?? '') === 'sudah' && str_contains((string) ($j['data']['pesan'] ?? ''), 'satu kali'), (string) $k);
        $this->fx['layanan_perpanjangan_sudah'] = $j;
        [$k, $j] = $this->apiLayanan('saya', [], [], $token);
        $this->cek('GET publik/layanan/saya → "Permintaan suratku" memuat Surat Perpanjangan PKL "Menunggu ACC"', $k === 200
            && in_array('Menunggu ACC', array_column(array_filter($j['data']['permintaan'] ?? [], static fn (array $x) => $x['jenis'] === SuratJenis::PERPANJANGAN), 'status_teks'), true), (string) $k);
        $this->fx['layanan_saya_sesudah'] = $j;

        $dir = (string) getenv('UJI_FIXTURE');
        if ($dir !== '' && is_dir($dir)) {
            foreach ($this->fx as $nama => $isi) {
                file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $nama . '.json', json_encode($isi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            }
            $this->cek('fixture Flutter ditulis ke ' . $dir . ' (' . count($this->fx) . ' berkas)', true);
        }
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function apiPkl(string $peran, string $metode, array $get, mixed ...$arg): array
    {
        \App\Libraries\ApiAuth::set(['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'role' => $peran]);
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/pkl'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $c = new \App\Controllers\Api\Pkl();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}(...$arg);

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function apiLayanan(string $metode, array $get, array $post, string $token = ''): array
    {
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/publik/layanan'), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod($post !== [] ? 'POST' : 'GET');
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        if ($token !== '') {
            $request->setHeader(\App\Libraries\LayananToken::HEADER, $token);
        }
        $c = new \App\Controllers\Api\Layanan();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}();

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private function buatSiswa(int $n): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('siswa')->insert([
            'nis' => self::NIS . $n, 'nama' => self::OLEH . ' ' . $n, 'jenis_kelamin' => 'P', 'kelas_id' => $this->kelasId, 'no_hp' => '08130000000' . $n,
            'status' => 'aktif', 'tanggal_lahir' => '2009-05-17', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /** @param list<int> $teman */
    private function buatAjuan(int $siswaId, string $perusahaan, array $teman, ?string $mulai, ?string $selesai): int
    {
        $data = [
            'perusahaan_nama' => $perusahaan, 'perusahaan_norm' => PklForm::normPerusahaan($perusahaan),
            'perusahaan_alamat' => 'Jl. Uji Perpanjangan No. 1', 'perusahaan_kota' => 'Bekasi', 'perusahaan_telepon' => null,
            'kontak_nama' => null, 'kontak_jabatan' => null, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai, 'hp' => '081300009999', 'tanggal_lahir' => null, 'teman' => [],
        ];
        $anggota = [['siswa_id' => $siswaId, 'kelas_id' => $this->kelasId, 'peran' => 'pengaju', 'hp' => '081300009999']];
        foreach ($teman as $t) {
            $anggota[] = ['siswa_id' => $t, 'kelas_id' => $this->kelasId, 'peran' => 'teman', 'hp' => null];
        }
        $r = (new PklAjuan())->kirimBaru($data, $anggota,
            ['oleh' => self::OLEH . ' Hubin', 'admin_id' => null, 'peran' => 'hubin', 'ip' => self::IP, 'sumber' => 'staf', 'aksi' => 'isi_atas_nama', 'status_awal' => 'disetujui', 'tahun_ajaran' => '2031/2032']);
        $id = (int) ($r['id'] ?? 0);
        if ($id > 0 && ($mulai !== null || $selesai !== null)) {
            $this->db->table('pkl_pengajuan')->where('id', $id)->update(['tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai]);
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private function ctx(string $peran): array
    {
        return ['oleh' => self::OLEH . ' ' . $peran, 'admin_id' => null, 'peran' => $peran, 'ip' => self::IP];
    }

    private function atur(array $set): void
    {
        $this->db->table('pkl_pengaturan')->where('id', 1)->update($set);
    }

    private function jumlah(string $tabel, array $where): int
    {
        return (int) $this->db->table($tabel)->where($where)->countAllResults();
    }

    private function isiDocx(string $biner): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ujipj');
        file_put_contents($tmp, $biner);
        $zip = new \ZipArchive();
        $xml = '';
        if ($zip->open($tmp) === true) {
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
        }
        @unlink($tmp);

        return html_entity_decode(strip_tags((string) preg_replace('/<\/w:p>/', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function panggil(string $kelas, string $peran, string $metode, array $get = [], array $post = [], mixed ...$arg): mixed
    {
        $request = new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'admin/surat'), null, new \CodeIgniter\HTTP\UserAgent());
        \Config\Services::injectMock('request', $request);
        session()->set('admin', ['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'username' => 'zzujipj', 'role' => $peran]);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        session()->remove('_ci_old_input');
        $c = new $kelas();
        $c->initController($request, service('response'), service('logger'));

        return $c->{$metode}(...$arg);
    }

    /** HTML halaman, atau "ALIH → {Location} {pesan galat}" bila controller mengalihkan (memudahkan membaca uji yang gagal). */
    private function teks(mixed $r): string
    {
        if ($r instanceof \CodeIgniter\HTTP\RedirectResponse) {
            return 'ALIH → ' . $r->getHeaderLine('Location') . ' ' . (string) session('error') . ' ' . json_encode(session('errors'), JSON_UNESCAPED_UNICODE);
        }

        // esc(…, 'attr') menulis entitas heksa (&#x2F;) — didekode supaya alamat di atribut bisa dicocokkan.
        return is_string($r) ? html_entity_decode($r, ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'BUKAN TEKS: ' . get_debug_type($r);
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function apiSurat(string $peran, string $metode, array $get, array $post, mixed ...$arg): array
    {
        \App\Libraries\ApiAuth::set(['id' => ['admin' => 1, 'hubin' => 2, 'operator' => 3][$peran] ?? 3, 'full_name' => self::OLEH . ' ' . $peran, 'role' => $peran]);
        $request = (new \CodeIgniter\HTTP\IncomingRequest(config('App'), new \CodeIgniter\HTTP\SiteURI(config('App'), 'api/v1/surat'), null, new \CodeIgniter\HTTP\UserAgent()))->withMethod('POST');
        \Config\Services::injectMock('request', $request);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $c = new \App\Controllers\Api\Surat();
        $c->initController($request, service('response'), service('logger'));
        $r = $c->{$metode}(...$arg);

        return [$r->getStatusCode(), json_decode((string) $r->getBody(), true) ?: []];
    }

    private function bersihkan(): void
    {
        if (! isset($this->db)) {
            $this->db = db_connect();
        }
        $this->db->table('surat_sekolah')->like('dibuat_nama', self::OLEH, 'after')->delete(); // CASCADE: siswa, riwayat, pkl_perpanjangan
        $this->db->table('surat_sekolah')->like('dibuat_nama', 'Siswa: ' . self::OLEH, 'after')->delete();
        $ids = array_map('intval', array_column($this->db->table('siswa')->select('id')->like('nis', self::NIS, 'after')->get()->getResultArray(), 'id'));
        if ($ids !== []) {
            $aj = array_map('intval', array_column($this->db->table('pkl_anggota')->select('pengajuan_id')->whereIn('siswa_id', $ids)->get()->getResultArray(), 'pengajuan_id'));
            if ($aj !== []) {
                $this->db->table('pkl_pengajuan')->whereIn('id', array_values(array_unique($aj)))->delete();
            }
            $this->db->table('siswa')->whereIn('id', $ids)->delete();
        }
        $this->db->table('pkl_pengajuan')->where('ip_address', self::IP)->delete();
        $this->db->table('pkl_perusahaan')->like('nama_norm', 'zzujipj', 'after')->delete();
        $this->db->table('audit_log')->like('deskripsi', self::OLEH)->delete();
        $this->db->table('audit_log')->like('deskripsi', 'ZZUJIPJ')->delete();
        if ($this->pengaturanAsli !== null) {
            $this->db->table('pkl_pengaturan')->where('id', 1)->update($this->pengaturanAsli);
            $this->pengaturanAsli = null;
        }
        $this->pulihkanPeran();
        PklHak::lupakan();
    }
}
