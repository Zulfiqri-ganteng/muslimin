<?php

namespace App\Controllers\Api;

use App\Libraries\LayananToken;
use App\Libraries\PklLapor;
use App\Models\PklPengaturanModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API publik LAPOR DITERIMA PKL (layanan LY4, aplikasi Android, tanpa akun) = form web `pkl/lapor` (Controllers\LaporPkl).
 * Aturan, pesan & penyimpanan SAMA (Libraries\PklLapor); pengganti izin sesi web = token `X-Izin-Token`
 * (LayananToken::buatIzin keperluan "pkl-lapor", umur PklLapor::TTL_IZIN) yang didapat dari cek. Kontrak:
 * docs/API-LAYANAN-SISWA.md bagian "LY4".
 *
 *   GET  publik/pkl/lapor                  bahan form: terbuka?, kelas per tingkat, hasil_ada, batas panjang
 *   GET  publik/pkl/lapor/siswa?kelas_id=  nama + status lapor (tanpa data perusahaan)
 *   POST publik/pkl/lapor/cek              siswa_id, hp → ringkasan ajuan & laporan + token
 *   POST publik/pkl/lapor/kirim            (X-Izin-Token) ajuan_id, hasil, diterima[], tanggal_mulai, tanggal_selesai, alamat, catatan
 */
class LaporPkl extends BaseApiController
{
    public const HEADER_IZIN = 'X-Izin-Token';

    private const TUJUAN = 'pkl-lapor';

    private array $p;
    private PklLapor $lib;

    public function __construct()
    {
        $this->p   = (new PklPengaturanModel())->ambil();
        $this->lib = new PklLapor();
    }

    public function index(): ResponseInterface
    {
        if (! PklLapor::terbuka($this->p)) {
            return $this->ok(['terbuka' => false], 'Form lapor sedang ditutup.');
        }
        $kelas = [];
        foreach ($this->lib->kelas() as $tingkat => $daftar) {
            $kelas[] = ['tingkat' => (string) $tingkat, 'kelas' => $daftar];
        }

        return $this->ok([
            'terbuka'      => true,
            'kelas'        => $kelas,
            'hasil_ada'    => PklLapor::kolomHasil(),
            'maks_alamat'  => PklLapor::MAKS_ALAMAT,
            'maks_catatan' => PklLapor::MAKS_CATATAN,
        ], 'Bahan form lapor.');
    }

    public function siswa(): ResponseInterface
    {
        if (! PklLapor::terbuka($this->p)) {
            return $this->failure('Form lapor diterima sedang ditutup.', 403, ['kode' => 'tutup']);
        }
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId <= 0) {
            return $this->failure('Pilih kelas terlebih dahulu.', 422);
        }

        return $this->ok($this->lib->siswaKelas($kelasId));
    }

    public function cek(): ResponseInterface
    {
        if (! PklLapor::terbuka($this->p)) {
            return $this->failure('Form lapor diterima sedang ditutup.', 403, ['kode' => 'tutup']);
        }
        if (! LayananToken::siap()) {
            return $this->failure('Layanan ini belum siap di server (kunci aplikasi belum diatur). Hubungi operator sekolah.', 503);
        }
        $in = $this->body();
        $r  = $this->lib->buktikan((int) ($in['siswa_id'] ?? 0), trim((string) ($in['hp'] ?? '')), $this->request->getIPAddress());
        if (! $r['ok']) {
            return $this->failure($r['pesan'], $r['http']);
        }
        $t = LayananToken::buatIzin(self::TUJUAN, $r['izin'], PklLapor::TTL_IZIN);

        return $this->ok($r['data'] + ['token' => $t['token'] ?? null, 'berlaku_sampai' => $t['berlaku_sampai'] ?? null], $r['pesan']);
    }

    public function kirim(): ResponseInterface
    {
        if (! PklLapor::terbuka($this->p)) {
            return $this->failure('Maaf, form lapor diterima sudah ditutup.', 403, ['kode' => 'tutup']);
        }
        $izin = LayananToken::bacaIzin(self::TUJUAN, $this->request->getHeaderLine(self::HEADER_IZIN));
        if ($izin === null) {
            return $this->failure('Sesi sudah habis. Pilih namamu dan ketik nomor HP lagi.', 403, ['kode' => 'sesi_habis']);
        }
        $r = $this->lib->kirim($this->body(), $this->request->getIPAddress(), $izin);
        if (! $r['ok']) {
            if (($r['galat'] ?? []) !== []) {
                return $this->invalid($r['galat'], $r['pesan']);
            }

            return $this->failure($r['pesan'], $r['http'], $r['http'] === 403 ? ['kode' => 'sesi_habis'] : null);
        }

        return $this->ok($r['data'], $r['pesan']);
    }
}
