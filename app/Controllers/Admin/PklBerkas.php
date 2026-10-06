<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\PklDocx;
use App\Libraries\PklImpor;
use App\Libraries\PklSurat;
use App\Models\AuditModel;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * PKL Tahap 4 — berkas: surat Word (satuan & massal), template Word sekolah, Excel Status Siswa,
 * impor riwayat PKL lama. Alamat template/impor ada di bawah admin/pkl/pengaturan & admin/pkl/impor,
 * yang ditolak untuk Hubin oleh Config\Peran ('kecuali'). Surat & Excel boleh untuk Hubin.
 */
class PklBerkas extends BaseController
{
    private const MAKS_SURAT_MASSAL = 300;

    private PklPengajuanModel $model;
    private PklSurat $surat;
    private AuditModel $audit;

    public function __construct()
    {
        $this->model = new PklPengajuanModel();
        $this->surat = new PklSurat();
        $this->audit = new AuditModel();
    }

    // =================================================================
    // Surat
    // =================================================================

    /** POST admin/pkl/{id}/surat — terbitkan (bila belum) + unduh surat satu ajuan. */
    public function surat($id)
    {
        $id    = (int) $id;
        $ajuan = $this->model->find($id);
        if ($ajuan === null) {
            return $this->ke('admin/pkl/daftar/disetujui', 'error', 'Ajuan tidak ditemukan.');
        }
        if ($ajuan['status'] !== 'disetujui') {
            return $this->ke('admin/pkl/' . $id, 'error', 'Surat hanya bisa dibuat untuk ajuan yang sudah DISETUJUI.');
        }

        $tanggal = trim((string) $this->request->getPost('tanggal_surat'));
        if ($tanggal === '') {
            $tanggal = date('Y-m-d');
        }
        $tgl = \DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);
        if ($tgl === false || $tgl->format('Y-m-d') !== $tanggal || (int) $tgl->format('Y') < (int) date('Y') - 1 || (int) $tgl->format('Y') > (int) date('Y') + 2) {
            return $this->ke('admin/pkl/' . $id, 'error', 'Tanggal surat tidak valid.');
        }

        return $this->unduh([$id], $tanggal, 'admin/pkl/' . $id);
    }

    /** POST admin/pkl/surat-massal — mode "terpilih" (ids[]) atau "belum" (belum dicetak / perlu cetak ulang). */
    public function suratMassal()
    {
        $mode = (string) $this->request->getPost('mode');
        $balik = 'admin/pkl/daftar/disetujui';

        if ($mode === 'terpilih') {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->request->getPost('ids')))));
            if ($ids === []) {
                return $this->ke($balik, 'error', 'Centang dulu ajuan yang suratnya mau diunduh.');
            }
        } else {
            $semua = array_map('intval', array_column(
                db_connect()->table('pkl_pengajuan')->select('id')->where('status', 'disetujui')->get()->getResultArray(),
                'id'
            ));
            if ($mode === 'semua') {
                // Semua ajuan yang disetujui (termasuk yang sudah pernah dicetak) — untuk cetak ulang massal.
                $ids = $semua;
                if ($ids === []) {
                    return $this->ke($balik, 'error', 'Belum ada ajuan yang disetujui.');
                }
            } else {
                $status = $this->surat->statusBanyak($semua);
                $ids    = array_values(array_filter($semua, static fn (int $i) => ! isset($status[$i]) || $status[$i]['perlu_ulang']));
                if ($ids === []) {
                    return $this->ke($balik, 'success', 'Semua surat sudah dicetak dan datanya tidak berubah. Tidak ada yang perlu diunduh. Mau mencetak ulang semuanya? Pakai tombol "Unduh SEMUA surat".');
                }
            }
        }
        if (count($ids) > self::MAKS_SURAT_MASSAL) {
            return $this->ke($balik, 'error', 'Terlalu banyak sekaligus (maksimal ' . self::MAKS_SURAT_MASSAL . ' surat). Pakai saringan/centang sebagian.');
        }

        // Urutan nomor = urutan persetujuan (yang lebih dulu disetujui mendapat nomor lebih kecil).
        $urut = array_map('intval', array_column(
            db_connect()->table('pkl_pengajuan')->select('id')->whereIn('id', $ids)->where('status', 'disetujui')
                ->orderBy('diputuskan_at', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray(),
            'id'
        ));

        return $this->unduh($urut, date('Y-m-d'), $balik);
    }

    /** @param list<int> $ids */
    private function unduh(array $ids, string $tanggal, string $balik)
    {
        $hasil = $this->surat->bangun($ids, $this->konteks(), $tanggal);
        if (! $hasil['ok']) {
            return $this->ke($balik, 'error', $hasil['pesan'] ?? 'Surat gagal dibuat.');
        }
        $this->audit->record('update', 'pkl_surat', $ids[0] ?? null, 'Surat PKL diunduh: ' . $hasil['jumlah'] . ' surat (' . $hasil['nama'] . ')');

        return $this->berkas($hasil['nama'], $hasil['biner']);
    }

    /**
     * Unduhan dari form: ikut menyetel cookie penanda selesai (nilainya = token dari halaman) supaya
     * layar "Memproses…" di halaman bisa ditutup — unduhan tidak memuat ulang halaman, jadi tanpa ini
     * layar itu berputar terus. Cookie dikirim lewat header (respons unduhan tak memproses cookie CI4).
     */
    private function berkas(string $nama, string $biner)
    {
        $respons = $this->response->download($nama, $biner)->setFileName($nama);
        $token   = (string) $this->request->getPost('unduh_token');
        if (preg_match('/^[0-9]{1,40}$/', $token) === 1) {
            $respons->setHeader('Set-Cookie', 'unduh_selesai=' . $token . '; Path=/; Max-Age=120; SameSite=Lax');
        }

        return $respons;
    }

    // =================================================================
    // Template Word sekolah (Operator/Admin)
    // =================================================================

    /** GET admin/pkl/pengaturan/template[?contoh=1] — unduh template aktif, atau contoh untuk diedit. */
    public function template()
    {
        if ($this->request->getGet('contoh') === '1') {
            return $this->response->download('Contoh Template Surat PKL.docx', $this->surat->contohTemplate())->setFileName('Contoh Template Surat PKL.docx');
        }
        $path = PklSurat::pathTemplate((new PklPengaturanModel())->ambil());
        if ($path === null) {
            return $this->ke('admin/pkl/pengaturan', 'error', 'Belum ada template yang diunggah. Unduh "contoh template" untuk memulai.');
        }

        return $this->response->download('Template Surat PKL.docx', (string) file_get_contents($path))->setFileName('Template Surat PKL.docx');
    }

    /** POST admin/pkl/pengaturan/template — unggah template Word milik sekolah. */
    public function unggahTemplate(): RedirectResponse
    {
        $balik = 'admin/pkl/pengaturan';
        $berkas = $this->request->getFile('template');
        if ($berkas === null || ! $berkas->isValid() || $berkas->hasMoved()) {
            return $this->ke($balik, 'error', 'Pilih berkas template Word (.docx) dulu.');
        }
        if (strtolower($berkas->getClientExtension()) !== 'docx' || $berkas->getSize() > 3 * 1024 * 1024) {
            return $this->ke($balik, 'error', 'Template harus berkas Word .docx berukuran maksimal 3 MB (bukan .doc / PDF).');
        }

        $sementara = $berkas->getTempName();
        $penanda   = PklDocx::penandaDi($sementara);
        if ($penanda === []) {
            return $this->ke($balik, 'error', 'Berkas ini tidak memuat penanda ${...}, atau bukan dokumen Word yang sah. Unduh "contoh template" dan sunting dari situ.');
        }
        $dikenal = array_merge(PklSurat::SKALAR, PklSurat::BARIS);
        $asing   = array_values(array_diff($penanda, $dikenal));

        // Uji isi dengan data contoh: template yang rusak ditolak SEKARANG, bukan saat staf mencetak surat.
        try {
            $v = array_fill_keys(PklSurat::SKALAR, 'Contoh');
            $baris = ['no' => '1', 'nama' => 'Contoh Siswa', 'nis' => '1', 'nisn' => '1', 'kelas' => 'XI', 'jurusan' => 'TKJ'];
            $biner = PklDocx::dariTemplate($sementara, [['v' => $v, 'siswa' => [$baris]]]);
            $dom   = new \DOMDocument();
            $zip   = new \ZipArchive();
            $tmp   = tempnam(sys_get_temp_dir(), 'pkt');
            file_put_contents($tmp, $biner);
            $zip->open($tmp);
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
            @unlink($tmp);
            if (! @$dom->loadXML($xml)) {
                throw new \RuntimeException('hasil isian bukan XML yang sah');
            }
        } catch (\Throwable $e) {
            return $this->ke($balik, 'error', 'Template tidak bisa dipakai (' . $e->getMessage() . '). Coba mulai dari "contoh template".');
        }

        $tujuan = PklSurat::dirBerkas() . PklSurat::BERKAS_TEMPLATE;
        if (! @copy($sementara, $tujuan)) {
            return $this->ke($balik, 'error', 'Template gagal disimpan di server (folder writable/pkl tidak bisa ditulis).');
        }
        (new PklPengaturanModel())->update(1, ['template_surat' => PklSurat::BERKAS_TEMPLATE]);
        $this->audit->record('update', 'pkl_pengaturan', 1, 'Template surat PKL diunggah (' . count($penanda) . ' penanda)');

        return $this->ke($balik, 'success', 'Template surat diunggah dan dipakai.'
            . ($asing !== [] ? ' Perhatian: penanda ini tidak dikenal dan akan tampil apa adanya: ${' . implode('}, ${', $asing) . '}.' : ''));
    }

    public function hapusTemplate(): RedirectResponse
    {
        $path = PklSurat::pathTemplate((new PklPengaturanModel())->ambil());
        if ($path !== null) {
            @unlink($path);
        }
        (new PklPengaturanModel())->update(1, ['template_surat' => null]);
        $this->audit->record('update', 'pkl_pengaturan', 1, 'Template surat PKL dihapus (kembali ke surat bawaan)');

        return $this->ke('admin/pkl/pengaturan', 'success', 'Template dihapus. Surat kembali memakai tampilan bawaan.');
    }

    // =================================================================
    // Excel Status Siswa
    // =================================================================

    public function siswaExcel()
    {
        $p       = (new PklPengaturanModel())->ambil();
        $tingkat = PklPengaturanModel::tingkatBoleh($p);
        $q       = IsianBantu::rapikan((string) $this->request->getGet('q'));
        $kelas   = (int) $this->request->getGet('kelas_id');
        $fase    = (string) $this->request->getGet('fase');

        [$rows] = $this->model->statusSiswa($tingkat, $kelas, $q, $fase, 100000, 1);
        $r      = $this->model->ringkasanSiswa($tingkat, $kelas);
        $label  = [
            'belum' => 'Belum mengisi', 'ditolak' => 'Ditolak (perlu ajukan ulang)', 'menunggu' => 'Menunggu ACC', 'perbaikan' => 'Perlu perbaikan',
            'belum_mulai' => 'Disetujui, belum mulai', 'sedang' => 'Sedang PKL', 'selesai' => 'Selesai PKL', 'disetujui' => 'Disetujui',
        ];
        $setting = (new SettingModel())->get();

        $x  = new Spreadsheet();
        $ws = $x->getActiveSheet()->setTitle('Status PKL Siswa');
        $ws->setCellValue('A1', 'STATUS PKL SISWA — ' . mb_strtoupper((string) ($setting['school_name'] ?? '')));
        $ws->setCellValue('A2', 'Tahun Pelajaran ' . ($setting['academic_year'] ?? '') . ' · tingkat ' . implode('/', $tingkat) . ' · dicetak ' . date('d-m-Y H:i'));
        $ws->setCellValue('A3', sprintf('Sudah mengisi: %d dari %d (%d%%)   |   Belum mengisi: %d   |   Sudah PKL (disetujui): %d (%d%%)   |   Belum PKL: %d',
            $r['sudah_isi'], $r['total'], $r['persen_isi'], $r['belum_isi'], $r['sudah_pkl'], $r['persen_pkl'], $r['belum_pkl']));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A3')->getFont()->setBold(true);

        $judul = ['No', 'Nama', 'Kelas', 'Status', 'Perusahaan', 'Kota', 'Mulai', 'Selesai', 'No. Bukti', 'Nomor Surat'];
        $ws->fromArray($judul, null, 'A5');
        $ws->getStyle('A5:J5')->getFont()->setBold(true);
        $ws->getStyle('A5:J5')->getFill()->setFillType('solid')->getStartColor()->setRGB('D9E2F3');
        $ws->getStyle('A5:J5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $no = 6;
        foreach ($rows as $i => $s) {
            $ws->fromArray([
                $i + 1, $s['nama'], $s['nama_kelas'], $label[$s['fase']] ?? $s['fase'], $s['perusahaan_nama'] ?? '', $s['perusahaan_kota'] ?? '',
                ! empty($s['tanggal_mulai']) ? IsianBantu::tanggalIndo($s['tanggal_mulai']) : '', ! empty($s['tanggal_selesai']) ? IsianBantu::tanggalIndo($s['tanggal_selesai']) : '',
                ! empty($s['ajuan_id']) ? PklPengajuanModel::kode((int) $s['ajuan_id']) : '', $s['nomor_surat'] ?? '',
            ], null, 'A' . $no++);
        }
        foreach (range('A', 'J') as $c) {
            $ws->getColumnDimension($c)->setAutoSize(true);
        }
        $ws->freezePane('A6');
        $ws->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getSheetView()->setZoomScale(100);

        $tmp = tempnam(sys_get_temp_dir(), 'pkx');
        (new Xlsx($x))->save($tmp);
        $biner = (string) file_get_contents($tmp);
        @unlink($tmp);
        $nama = 'Status PKL Siswa ' . date('Y-m-d') . '.xlsx';

        return $this->response->download($nama, $biner)->setFileName($nama);
    }

    // =================================================================
    // Impor riwayat PKL lama (Operator/Admin)
    // =================================================================

    public function impor()
    {
        return view('admin/pkl/impor', $this->dasar('Impor Riwayat PKL') + ['pratinjau' => null, 'token' => '']);
    }

    public function imporContoh()
    {
        return $this->response->download('Contoh Impor Riwayat PKL.xlsx', PklImpor::contoh())->setFileName('Contoh Impor Riwayat PKL.xlsx');
    }

    public function imporPratinjau()
    {
        $berkas = $this->request->getFile('berkas');
        if ($berkas === null || ! $berkas->isValid() || $berkas->hasMoved()) {
            return $this->ke('admin/pkl/impor', 'error', 'Pilih berkas Excel (.xlsx) atau CSV dulu.');
        }
        if (! in_array(strtolower($berkas->getClientExtension()), ['xlsx', 'xls', 'csv'], true) || $berkas->getSize() > 2 * 1024 * 1024) {
            return $this->ke('admin/pkl/impor', 'error', 'Berkas harus Excel (.xlsx/.xls) atau CSV, maksimal 2 MB.');
        }

        try {
            $pratinjau = (new PklImpor())->baca($berkas->getTempName());
        } catch (\RuntimeException $e) {
            return $this->ke('admin/pkl/impor', 'error', $e->getMessage());
        }

        $token = bin2hex(random_bytes(16));
        $dir   = PklSurat::dirBerkas();
        foreach (glob($dir . 'impor_*.json') ?: [] as $lama) { // buang pratinjau yang terbengkalai > 1 hari
            if (filemtime($lama) < time() - 86400) {
                @unlink($lama);
            }
        }
        file_put_contents($dir . 'impor_' . $token . '.json', json_encode(['admin' => (int) session('admin.id'), 'data' => $pratinjau], JSON_UNESCAPED_UNICODE));

        return view('admin/pkl/impor', $this->dasar('Impor Riwayat PKL') + ['pratinjau' => $pratinjau, 'token' => $token]);
    }

    public function imporSimpan(): RedirectResponse
    {
        $token = (string) $this->request->getPost('token');
        $path  = PklSurat::dirBerkas() . 'impor_' . $token . '.json';
        if (! preg_match('/^[a-f0-9]{32}$/', $token) || ! is_file($path)) {
            return $this->ke('admin/pkl/impor', 'error', 'Pratinjau sudah kedaluwarsa. Unggah berkasnya lagi.');
        }
        $isi = json_decode((string) file_get_contents($path), true);
        if (! is_array($isi) || (int) ($isi['admin'] ?? 0) !== (int) session('admin.id') || ! isset($isi['data']['kelompok'])) {
            return $this->ke('admin/pkl/impor', 'error', 'Pratinjau tidak valid. Unggah berkasnya lagi.');
        }
        @unlink($path); // sekali pakai: tekan Simpan dua kali tidak menggandakan data

        $hasil = (new PklImpor())->simpan($isi['data'], $this->konteks());
        $this->audit->record('create', 'pkl_pengajuan', null, 'Impor riwayat PKL: ' . $hasil['dibuat'] . ' ajuan, ' . $hasil['siswa'] . ' siswa' . ($hasil['gagal'] ? ', ' . count($hasil['gagal']) . ' gagal' : ''));

        $pesan = 'Impor selesai: ' . $hasil['dibuat'] . ' ajuan (' . $hasil['siswa'] . ' siswa) dibuat sebagai Disetujui.';
        $redir = $this->ke('admin/pkl/daftar/disetujui', 'success', $pesan);

        return $hasil['gagal'] !== [] ? $redir->with('errors', $hasil['gagal']) : $redir;
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private function dasar(string $judul): array
    {
        $peran = (string) (session('admin')['role'] ?? '');

        return [
            'title' => $judul, 'tab' => 'impor', 'peran' => $peran, 'p' => (new PklPengaturanModel())->ambil(),
            'bolehPengaturan' => HakAkses::boleh($peran, 'admin/pkl/pengaturan'), 'bolehHapus' => HakAkses::boleh($peran, 'admin/pkl/hapus'),
            'hitungTab' => $this->model->hitungStatus(),
        ];
    }

    private function konteks(): array
    {
        $a = (array) session('admin');

        return [
            'oleh' => (string) ($a['full_name'] ?? 'Staf'), 'admin_id' => ((int) ($a['id'] ?? 0)) ?: null,
            'peran' => (string) ($a['role'] ?? ''), 'ip' => $this->request->getIPAddress(),
        ];
    }

    private function ke(string $alamat, string $jenis, string $pesan): RedirectResponse
    {
        return redirect()->to(site_url($alamat))->with($jenis, $pesan);
    }
}
