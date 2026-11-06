<?php

namespace App\Controllers\Api;

use App\Controllers\Biodata as BiodataWeb;
use App\Libraries\BiodataForm;
use App\Libraries\LayananToken;
use App\Models\BiodataIsianModel;
use App\Models\SiswaModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * API publik ISI BIODATA SISWA (layanan LY6, aplikasi Android, tanpa akun) = form web `biodata` (Controllers\Biodata). TURUNAN
 * controller web itu, jadi aturan (BiodataForm), pesan & pengamannya (satu isian per siswa, NISN unik, buka ulang dengan NISN /
 * tanggal lahir + LoginThrottle) SAMA persis. Yang diganti hanya:
 *   - isian JSON aplikasi dimasukkan ke POST (trait IsianJsonSebagaiPost);
 *   - izin kirim perbaikan (web: sesi 1 jam sesudah "buka") = token `X-Izin-Token` (LayananToken::buatIzin keperluan "biodata-buka");
 *   - selesai kirim → ringkasan halaman "terkirim" langsung di respons (web: flashdata + pengalihan ke biodata/selesai);
 *   - JSON = amplop API {status, ok, message, data}: galat per kolom di data.errors, catatan sekolah (buka) di data.catatan.
 * Bahan form (label, bagian, kolom wajib, pilihan) dikirim dari BiodataForm/SiswaModel — sumber yang sama dengan halaman web.
 * Kontrak: docs/API-LAYANAN-SISWA.md bagian "LY6". Uji: php spark dev:uji-biodata-publik (jalur web & API, skenario sama).
 *
 *   GET  publik/biodata                   bahan form (terbuka, kelas per tingkat, label/bagian/wajib, pilihan, rentang tahun)
 *   GET  publik/biodata/siswa?kelas_id=   nama + JK + status isian
 *   POST publik/biodata/buka              siswa_id, nisn | tanggal_lahir → isian lama + catatan sekolah + token
 *   POST publik/biodata/kirim             isian (+ X-Izin-Token untuk perbaikan) → ringkasan
 */
class BiodataPublik extends BiodataWeb
{
    use IsianJsonSebagaiPost;

    private const TUJUAN = 'biodata-buka';

    private ?string $tokenBaru = null;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->pakaiIsianJson($request);
    }

    /** Bahan form (pengganti halaman `biodata`). */
    public function index()
    {
        if (! BiodataIsianModel::formTerbuka($this->setting)) {
            return $this->json(['ok' => true, 'message' => 'Pengisian biodata sedang ditutup.', 'data' => [
                'terbuka'     => false,
                'judul_tutup' => 'Pengisian sedang ditutup',
                'pesan_tutup' => 'Formulir biodata siswa belum dibuka atau masa pengisian sudah berakhir. Tunggu informasi dari wali kelas, atau hubungi operator sekolah.',
            ]]);
        }
        $kelas = [];
        foreach ($this->daftarKelas() as $tingkat => $daftar) {
            $kelas[] = ['tingkat' => (string) $tingkat, 'kelas' => $daftar];
        }
        $thn = (int) date('Y');

        return $this->json(['ok' => true, 'message' => 'Bahan form biodata.', 'data' => [
            'terbuka'         => true,
            'kelas'           => $kelas,
            'sekolah'         => (string) ($this->setting['school_name'] ?? ''),
            'label'           => BiodataForm::LABEL,
            'bagian'          => BiodataForm::BAGIAN,
            'wajib'           => BiodataForm::WAJIB,
            'agama'           => SiswaModel::AGAMA,
            'status_keluarga' => SiswaModel::STATUS_KELUARGA,
            'pekerjaan'       => SiswaModel::PEKERJAAN,
            'tahun_lahir'     => [$thn - 40, $thn - 8],
            'tahun_terima'    => [2010, $thn + 1],
        ]]);
    }

    public function buka(): ResponseInterface
    {
        if (! LayananToken::siap()) {
            return $this->json(['ok' => false, 'message' => 'Layanan ini belum siap di server (kunci aplikasi belum diatur). Hubungi operator sekolah.'], 503);
        }

        return parent::buka();
    }

    protected function izinkanBuka(int $siswaId): void
    {
        $this->tokenBaru = LayananToken::buatIzin(self::TUJUAN, ['siswa' => $siswaId], self::BUKA_TTL)['token'] ?? null;
    }

    protected function sudahDibuka(int $id): bool
    {
        $izin = LayananToken::bacaIzin(self::TUJUAN, $this->request->getHeaderLine(LaporPkl::HEADER_IZIN));

        return $izin !== null && (int) ($izin['siswa'] ?? 0) === $id;
    }

    protected function kirimSelesai(int $siswaId, array $info): ResponseInterface
    {
        return $this->json(['ok' => true, 'message' => ! empty($info['revisi']) ? 'Perbaikan terkirim.' : 'Biodata terkirim.', 'data' => $info]);
    }

    protected function json(array $body, int $status = 200): ResponseInterface
    {
        $ok   = (bool) ($body['ok'] ?? false);
        $data = $body['data'] ?? null;
        if (isset($body['errors'])) {
            $data = ['errors' => $body['errors']];
        }
        if (array_key_exists('catatan', $body)) {
            $data = (array) $data + ['catatan' => $body['catatan']];
        }
        if ($ok && $this->tokenBaru !== null) {
            $data = (array) $data + ['token' => $this->tokenBaru];
        }

        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store')->setJSON([
            'status'  => $ok ? 'success' : 'error',
            'ok'      => $ok,
            'message' => (string) ($body['message'] ?? ($ok ? 'Berhasil' : 'Gagal')),
            'data'    => $data,
        ]);
    }
}
