<?php

namespace App\Controllers\Api;

use App\Libraries\IsianBantu;
use App\Libraries\LayananSiswa;
use App\Libraries\LayananToken;
use App\Libraries\SuratPerpanjangan;
use App\Models\PklPengajuanModel;
use App\Models\PklPengaturanModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API PUBLIK Layanan Siswa (tanpa akun) untuk layar Android — padanan halaman web layanan/* (Controllers\Layanan). Logika SAMA
 * (Libraries\LayananSiswa, SuratPerpanjangan); pengganti sesi web = token `X-Layanan-Token` (Libraries\LayananToken) yang didapat
 * sesudah bukti diri NISN / tanggal lahir (dibatasi LoginThrottle seperti web). Dokumen: docs/API-LAYANAN-SISWA.md.
 *
 *   GET  publik/layanan/kelas?tujuan=        kelas per tingkat (tujuan "izin_tka" = hanya XII)
 *   GET  publik/layanan/siswa?kelas_id=      nama siswa aktif satu kelas (id + nama saja)
 *   POST publik/layanan/masuk                {siswa_id, nisn | tanggal_lahir} → {token, berlaku_sampai, siswa}
 *   GET  publik/layanan/saya                 (token) profil + layanan surat (boleh/alasan) + "Permintaan suratku"
 *   GET  publik/layanan/perpanjangan         (token) bahan formulir Surat Perpanjangan PKL
 *   POST publik/layanan/perpanjangan         (token) kirim permintaan
 */
class Layanan extends BaseApiController
{
    private LayananSiswa $lib;
    private array $p;

    public function __construct()
    {
        $this->lib = new LayananSiswa();
        $this->p   = (new PklPengaturanModel())->ambil();
    }

    public function kelas(): ResponseInterface
    {
        $tujuan = LayananSiswa::tujuanSah((string) $this->request->getGet('tujuan'));
        $out    = [];
        foreach ($this->lib->kelas($this->p, $tujuan) as $tingkat => $kelas) {
            $out[] = ['tingkat' => (string) $tingkat, 'kelas' => $kelas];
        }

        return $this->ok($out, 'Kelas yang dilayani Layanan Siswa.');
    }

    public function siswa(): ResponseInterface
    {
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId <= 0) {
            return $this->invalid(['kelas_id' => 'Pilih kelas terlebih dahulu.'], 'Pilih kelas terlebih dahulu.');
        }
        $data = $this->lib->siswaKelas($kelasId, $this->p);

        return $data === [] ? $this->missing('Tidak ada siswa di kelas ini. Pilih kelas lain.') : $this->ok($data, 'Nama siswa kelas ini.');
    }

    public function masuk(): ResponseInterface
    {
        if (! LayananToken::siap()) {
            return $this->failure('Layanan siswa lewat aplikasi belum diaktifkan sekolah (kunci aplikasi belum diatur). Gunakan situs web.', 503);
        }
        $in = $this->body();
        $id = (int) ($in['siswa_id'] ?? 0);
        $r  = $this->lib->cocokkan($id, (string) ($in['nisn'] ?? ''), (string) ($in['tanggal_lahir'] ?? ''), $this->request->getIPAddress(), $this->p);
        if (! $r['ok']) {
            return $r['http'] === 422 ? $this->invalid(['bukti' => $r['pesan']], $r['pesan']) : $this->failure($r['pesan'], $r['http']);
        }
        $t = LayananToken::buat($id);

        return $this->ok($t + ['siswa' => $this->profil((array) $this->lib->profil($id))], $r['pesan']);
    }

    public function saya(): ResponseInterface
    {
        [$s, $tolak] = $this->siswaToken();
        if ($tolak !== null) {
            return $tolak;
        }
        $kel   = $this->lib->kelayakan($s, $this->p);
        $surat = [];
        foreach (LayananSiswa::SURAT as $kunci => [$judul, $ket, $ikon, $jenis]) {
            $surat[] = ['kunci' => $kunci, 'judul' => $judul, 'ket' => $ket, 'ikon' => $ikon, 'jenis' => $jenis,
                'siap' => $kel[$kunci]['siap'], 'boleh' => $kel[$kunci]['boleh'], 'alasan' => $kel[$kunci]['alasan'],
                // Formulir yang sudah punya API (lainnya dibuka di web layanan/surat/{kunci}).
                'api' => $kunci === 'perpanjangan', 'url_web' => site_url('layanan/surat/' . $kunci)];
        }

        return $this->ok(['siswa' => $this->profil($s), 'surat' => $surat, 'permintaan' => $this->lib->permintaan((int) $s['id'])], 'Layanan Siswa.');
    }

    /** GET publik/layanan/perpanjangan — sama dengan halaman web layanan/surat/perpanjangan. */
    public function perpanjangan(): ResponseInterface
    {
        [$s, $tolak] = $this->siswaToken();
        if ($tolak !== null) {
            return $tolak;
        }
        $k = $this->lib->kelayakan($s, $this->p)['perpanjangan'];
        if (! $k['boleh']) {
            return $this->failure($k['alasan'], 409, ['kode' => 'tidak_boleh']);
        }
        $f   = $this->lib->formPerpanjangan($s, $this->p);
        $j   = $f['jendela'];
        $sud = $f['sudah'];
        // Status formulir untuk layar: isi | sudah | ditarik | dini | lewat (selain "isi" tampilkan kalimat saja, tanpa formulir).
        $status = $sud !== null ? 'sudah' : ($f['ditarik'] !== null ? 'ditarik' : ($j !== null && $j['status'] !== 'buka' ? $j['status'] : 'isi'));

        return $this->ok([
            'status'       => $status,
            'pesan'        => $this->kalimatStatus($status, $f),
            'perusahaan'   => $f['perusahaan'],
            'ajuan'        => $f['ajuan'] !== null ? ['id' => (int) $f['ajuan']['id'], 'kode' => $f['ajuan']['kode'], 'alamat' => $f['ajuan']['alamat'], 'mulai' => $f['ajuan']['mulai']] : null,
            // null = belum tercatat di sekolah → layar meminta isian `selesai_lama` (tanpa pilihan cepat).
            'selesai_lama' => $f['lama'],
            'sisa_hari'    => $f['lama'] !== null ? SuratPerpanjangan::selisih(date('Y-m-d'), $f['lama']) : null,
            'jendela'      => $j,
            'setelan'      => $f['setelan'],
            'sudah'        => $sud !== null ? ['surat_id' => $sud['surat_id'], 'kode' => $sud['kode'], 'status' => $sud['status'], 'selesai_baru' => $sud['selesai_baru'], 'berlaku' => $sud['berlaku']] : null,
            'ditarik'      => $f['ditarik'],
            'cepat'        => array_map(static fn (array $c): array => $c + ['teks' => IsianBantu::tanggalIndo($c['tanggal'])], $f['cepat']),
            'alasan_saran' => array_map(static fn (array $a, int $i): array => ['kode' => (string) $i, 'kalimat' => $a[0], 'label' => $a[1]], SuratPerpanjangan::ALASAN, array_keys(SuratPerpanjangan::ALASAN)),
            'teman'        => $f['teman'],
            'maks_hari'    => SuratPerpanjangan::MAKS_HARI,
        ], 'Formulir Surat Perpanjangan PKL.');
    }

    /** POST publik/layanan/perpanjangan — {pilih_tanggal: Y-m-d | "lain", selesai_baru?, selesai_lama?, alasan_pilih, alasan_lain?, teman: [id…]} */
    public function ajukanPerpanjangan(): ResponseInterface
    {
        [$s, $tolak] = $this->siswaToken();
        if ($tolak !== null) {
            return $tolak;
        }
        $r = $this->lib->ajukanPerpanjangan((int) $s['id'], $this->body(), $this->request->getIPAddress(), $this->p);
        if (! $r['ok']) {
            if ($r['kode'] === 'sudah') {
                return $this->failure($r['pesan'], 409, ['kode' => 'sudah', 'errors' => $r['galat'] ?? []]);
            }
            if ($r['kode'] === 'gagal') {
                return $this->failure($r['pesan'], 500);
            }

            return ($r['galat'] ?? []) !== [] ? $this->invalid($r['galat'], $r['pesan']) : $this->failure($r['pesan'], 409, ['kode' => 'tidak_boleh']);
        }

        return $this->created(['surat_id' => $r['surat_id'], 'kode' => $r['kode'], 'kode_surat' => \App\Models\SuratSekolahModel::kode((int) $r['surat_id'])], $r['pesan']);
    }

    // ----------------------------------------------------------------- pembantu

    /** @return array{0: array<string, mixed>|null, 1: ResponseInterface|null} */
    private function siswaToken(): array
    {
        $id = LayananToken::baca($this->request->getHeaderLine(LayananToken::HEADER));
        $s  = $id !== null ? $this->lib->profil($id) : null;
        if ($s === null) {
            return [null, $this->failure('Sesi Layanan Siswa habis atau tidak sah. Masuk lagi dengan NISN atau tanggal lahir.', 401, ['kode' => 'masuk_lagi'])];
        }

        return [$s, null];
    }

    /** Profil siswa tanpa NISN/tanggal lahir. @return array<string, mixed> */
    private function profil(array $s): array
    {
        $a = $s['ajuan'] ?? null;

        return [
            'id' => (int) ($s['id'] ?? 0), 'nama' => (string) ($s['nama'] ?? ''), 'kelas' => (string) ($s['nama_kelas'] ?? ''), 'tingkat' => (string) ($s['tingkat'] ?? ''),
            'ajuan' => is_array($a) ? ['id' => (int) $a['id'], 'kode' => PklPengajuanModel::kode((int) $a['id']), 'status' => (string) $a['status'], 'perusahaan' => (string) ($a['perusahaan_nama'] ?? '')] : null,
        ];
    }

    /** Kalimat yang sama dengan halaman web untuk status formulir selain "isi". */
    private function kalimatStatus(string $status, array $f): string
    {
        $tgl = static fn (?string $t): string => IsianBantu::tanggalIndo((string) $t);

        return match ($status) {
            'sudah'   => 'Kamu sudah punya Surat Perpanjangan PKL (' . $f['sudah']['kode'] . ') sampai ' . $tgl($f['sudah']['selesai_baru']) . '. Perpanjangan PKL hanya boleh satu kali.',
            'ditarik' => 'Kamu sedang ditarik dari tempat PKL ini (' . $f['ditarik'] . '), jadi surat perpanjangan tidak bisa diminta. Hubungi operator sekolah bila ada yang salah.',
            'dini'    => 'PKL-mu di ' . $f['perusahaan'] . ' berakhir ' . $tgl($f['lama']) . '. Surat perpanjangan bisa diminta mulai ' . $tgl($f['jendela']['buka_mulai']) . '.',
            'lewat'   => 'PKL-mu di ' . $f['perusahaan'] . ' sudah berakhir ' . $tgl($f['lama']) . ' dan batas meminta surat perpanjangan sudah lewat (' . $tgl($f['jendela']['tutup']) . '). Hubungi operator sekolah.',
            default   => '',
        };
    }
}
