<?php

namespace App\Controllers\Api;

use App\Controllers\Pkl as PklWeb;
use App\Libraries\LayananToken;
use App\Libraries\PklLapor;
use App\Models\PklPengaturanModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * API publik AJUKAN TEMPAT PKL (layanan LY5, aplikasi Android, tanpa akun) = form web `pkl` (Controllers\Pkl). TURUNAN controller web
 * itu, jadi aturan, pesan, pengaman (batas kiriman per IP, LoginThrottle, satu siswa satu ajuan, pesan terkunci) & penyimpanannya SAMA
 * persis. Yang diganti hanya:
 *   - isian JSON aplikasi dimasukkan ke POST (logika web membaca getPost);
 *   - izin kirim perbaikan (web: sesi 1 jam sesudah "buka") = token `X-Izin-Token` (LayananToken::buatIzin keperluan "pkl-ajukan");
 *   - selesai kirim → ringkasan halaman "terkirim" langsung di respons (web: flashdata + pengalihan ke pkl/selesai);
 *   - JSON = amplop API {status, ok, message, data}: galat per kolom di data.errors, catatan sekolah (buka) di data.catatan.
 * Kontrak: docs/API-LAYANAN-SISWA.md bagian "LY5". Uji: php spark dev:uji-pkl-publik (jalur web & API, skenario sama).
 *
 *   GET  publik/pkl                       bahan form (terbuka / alasan tutup, kelas per tingkat, maks siswa, batas hari)
 *   GET  publik/pkl/siswa?kelas_id=       nama + status PKL
 *   GET  publik/pkl/perusahaan?q=         saran perusahaan yang pernah disetujui
 *   POST publik/pkl/buka                  siswa_id, hp → isi ajuan perbaikan + catatan sekolah + token
 *   POST publik/pkl/kirim                 isian (+ ajuan_id & X-Izin-Token untuk perbaikan) → ringkasan
 */
class PklAjukan extends PklWeb
{
    use IsianJsonSebagaiPost;

    private const TUJUAN = 'pkl-ajukan';

    /** Kalimat halaman tutup web (`pkl/tutup.php`) per alasan. */
    private const TUTUP = [
        'belum_dibuka'  => ['Pengajuan PKL Belum Dibuka', 'Formulir pengajuan PKL belum dibuka oleh sekolah. Tunggu informasi dari wali kelas atau Waka Hubin, lalu buka tautan ini lagi.'],
        'sudah_ditutup' => ['Masa Pengajuan Sudah Berakhir', 'Masa pengisian formulir pengajuan PKL sudah berakhir. Jika kamu belum mengajukan, hubungi wali kelas, operator sekolah, atau Waka Hubin.'],
        'belum_siap'    => ['Formulir Sedang Disiapkan', 'Sekolah masih menyiapkan formulir pengajuan PKL. Coba buka lagi nanti, atau tunggu informasi dari wali kelas.'],
    ];

    private ?string $tokenBaru = null;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->pakaiIsianJson($request);
    }

    /** Bahan form (pengganti halaman `pkl`). */
    public function index()
    {
        $dasar  = ['lapor_terbuka' => PklLapor::terbuka($this->p)];
        $alasan = PklPengaturanModel::alasanTutup($this->p);
        if ($alasan !== null) {
            [$judul, $pesan] = self::TUTUP[$alasan] ?? self::TUTUP['belum_dibuka'];

            return $this->json(['ok' => true, 'message' => 'Pengisian PKL sedang ditutup.', 'data' => $dasar + [
                'terbuka' => false, 'alasan' => $alasan, 'judul_tutup' => $judul, 'pesan_tutup' => $pesan,
            ]]);
        }
        $kelas = [];
        foreach ($this->daftarKelas() as $tingkat => $daftar) {
            $kelas[] = ['tingkat' => (string) $tingkat, 'kelas' => $daftar];
        }

        return $this->json(['ok' => true, 'message' => 'Bahan form pengajuan PKL.', 'data' => $dasar + [
            'terbuka'    => true,
            'kelas'      => $kelas,
            'maks_siswa' => PklPengaturanModel::maksSiswa($this->p),
            'batas_hari' => PklPengaturanModel::batasHari($this->p),
        ]]);
    }

    public function buka(): ResponseInterface
    {
        if (! LayananToken::siap()) {
            return $this->json(['ok' => false, 'message' => 'Layanan ini belum siap di server (kunci aplikasi belum diatur). Hubungi operator sekolah.'], 503);
        }

        return parent::buka();
    }

    protected function izinkanBuka(int $ajuanId): void
    {
        $this->tokenBaru = LayananToken::buatIzin(self::TUJUAN, ['ajuan' => $ajuanId], self::BUKA_TTL)['token'] ?? null;
    }

    protected function sudahDibuka(int $ajuanId): bool
    {
        $izin = LayananToken::bacaIzin(self::TUJUAN, $this->request->getHeaderLine(LaporPkl::HEADER_IZIN));

        return $izin !== null && (int) ($izin['ajuan'] ?? 0) === $ajuanId;
    }

    protected function kirimSelesai(int $ajuanId, array $info): ResponseInterface
    {
        return $this->json(['ok' => true, 'message' => ! empty($info['revisi']) ? 'Perbaikan terkirim.' : 'Ajuan PKL terkirim.', 'data' => $info]);
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
