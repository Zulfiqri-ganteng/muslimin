<?php

namespace App\Controllers\Api;

use App\Libraries\HakAkses;
use App\Libraries\IsianBantu;
use App\Libraries\SuratAcara;
use App\Libraries\SuratBerkas;
use App\Libraries\SuratJenis;
use App\Libraries\SuratKeputusan;
use App\Libraries\SuratPeriksa;
use App\Libraries\SuratPernyataan;
use App\Libraries\SuratPkl;
use App\Libraries\SuratSekolah;
use App\Models\PklPengaturanModel;
use App\Models\SettingModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Peran;

/**
 * Surat Sekolah untuk aplikasi Android (Operator, Waka Hubin, Admin) — cermin Admin\Surat + SuratIzin + SuratPernyataan +
 * SuratPkl (Surat Sekolah L8). Seluruh aturan ada di library bersama (SuratSekolah, SuratKeputusan, SuratBerkas, SuratAcara,
 * SuratPernyataan, SuratPkl, SuratPeriksa), jadi hasil web & aplikasi selalu sama. Dokumentasi: docs/API-SURAT.md.
 *
 * Gerbang: ApiAuthFilter hanya meloloskan peran ber-akses "surat" (Config\Peran 'api_akses'). Hak per aksi (diatur Admin di
 * PKL → Hak Akses): buat / ubah / ajukan ulang / batalkan / unduh = 'surat_sekolah' (bawaan Operator); ACC / kembalikan /
 * batal ACC / ACC massal = 'acc' (bawaan Waka Hubin; Admin cadangan wajib wakil=1). Daftar & detail terbuka bagi ketiganya.
 *
 * Rute (/api/v1, Bearer):
 *   GET  surat/meta                              kamus: jenis, status, label isian, aturan, tahun, hitungan, hak peran ini
 *   GET  surat?status=&jenis=&tahun=&q=&page=&per=   Daftar Surat (+ meta.hitung per status)
 *   GET  surat/{id}                              detail: isian, siswa, peringatan, riwayat, hak atas surat ini
 *   POST surat/{id}/acc|kembalikan|batal-acc|ajukan-ulang|batal   {catatan?, wakil?, paham?}
 *   POST surat/acc-massal                        {mode: terpilih|aman, ids?, wakil?}
 *   POST surat/{id}/unduh                        → berkas .docx (nomor terbit saat unduhan pertama)
 *   POST surat/unduh-massal                      {jenis, mode: terpilih|belum|semua, ids?} → .docx / .zip
 *   GET  surat/form/{slug}                       pilihan & isian awal formulir (slug: asts|tka|pernyataan-ortu|balasan|penarikan)
 *   GET  surat/siswa?kelas_id[]=                 siswa aktif per kelas (Pernyataan "siswa pilihan")
 *   POST surat/{slug}                            buat surat (isian sama dengan formulir web)
 *   POST surat/{id}/ubah                         ubah data surat yang belum bernomor (ASTS, TKA, Balasan, Penarikan)
 */
class Surat extends BaseApiController
{
    private const PER_MAKS = 100;

    private SuratSekolah $svc;
    private array $p;

    public function __construct()
    {
        $this->svc = new SuratSekolah();
        $this->p   = (new PklPengaturanModel())->ambil();
    }

    // ================================================================= kamus

    public function meta(): ResponseInterface
    {
        $jenis = [];
        foreach (SuratJenis::kode() as $k) {
            $jenis[] = [
                'kode' => $k, 'slug' => self::slug($k), 'label' => SuratJenis::label($k), 'singkat' => SuratJenis::singkat($k),
                'bernomor' => SuratJenis::bernomor($k), 'perlu_acc' => SuratJenis::perluAcc($k, $this->p),
                'siap' => SuratJenis::siap($k), 'template_lengkap' => SuratJenis::templateLengkap($k),
            ];
        }

        return $this->ok([
            'jenis'        => $jenis,
            'status'       => array_map(static fn (array $v): string => $v[0], SuratSekolahModel::TAMPIL_STATUS),
            'aksi_riwayat' => SuratSekolah::AKSI,
            'label_isi'    => SuratJenis::LABEL_ISI,
            'aturan'       => [
                'maks_acc_massal' => SuratKeputusan::MAKS_ACC_MASSAL, 'maks_unduh' => SuratBerkas::MAKS_UNDUH, 'maks_siswa' => SuratSekolah::MAKS_SISWA,
                'maks_hari_acara' => SuratAcara::MAKS_HARI, 'maks_perusahaan' => SuratAcara::MAKS_PERUSAHAAN,
                'maks_pilihan_pernyataan' => SuratPernyataan::MAKS_PILIHAN, 'maks_ajuan_balasan' => SuratPkl::MAKS_AJUAN, 'maks_hari_pkl' => SuratPkl::MAKS_HARI_PKL,
            ],
            'tahun'        => $this->svc->tahunTersedia(),
            'hitung'       => $this->svc->hitungStatus(),
            'hak'          => $this->hakUmum(),
        ], 'Kamus Surat Sekolah.');
    }

    // ================================================================= daftar & detail

    public function daftar(): ResponseInterface
    {
        $f = [
            'status' => (string) $this->request->getGet('status'),
            'jenis'  => (string) $this->request->getGet('jenis'),
            'tahun'  => (int) $this->request->getGet('tahun'),
            'q'      => mb_substr(IsianBantu::rapikan((string) $this->request->getGet('q')), 0, 80),
        ];
        $galat = [];
        if ($f['status'] !== '' && ! in_array($f['status'], SuratSekolahModel::STATUS, true)) {
            $galat['status'] = 'Status harus salah satu: ' . implode(', ', SuratSekolahModel::STATUS) . ' (kosong = semua).';
        }
        if ($f['jenis'] !== '' && ! SuratJenis::ada($f['jenis'])) {
            $galat['jenis'] = 'Jenis surat tidak dikenal (lihat surat/meta).';
        }
        if ($f['tahun'] !== 0 && ($f['tahun'] < 2000 || $f['tahun'] > 2100)) {
            $galat['tahun'] = 'Tahun tidak valid.';
        }
        if ($galat !== []) {
            return $this->invalid($galat);
        }

        $per   = max(1, min(self::PER_MAKS, (int) ($this->request->getGet('per') ?: 20)));
        $hasil = $this->svc->daftar($f, max(1, (int) $this->request->getGet('page')), $per);
        $ulang = $this->svc->perluUlangBanyak($hasil['rows'], $this->p);

        return $this->ok(array_map(fn (array $r): array => $this->ringkas($r, $ulang), $hasil['rows']), 'Daftar surat.', [
            'pagination' => ['page' => $hasil['page'], 'per_page' => $per, 'total' => $hasil['total'], 'total_pages' => $hasil['jml_hal']],
            'hitung'     => $this->svc->hitungStatus(),
        ]);
    }

    public function detail($id): ResponseInterface
    {
        $m = $this->svc->muat((int) $id);
        if ($m === null) {
            return $this->missing('Surat tidak ditemukan (mungkin sudah dihapus).');
        }
        $s      = $m['surat'];
        $jenis  = (string) $s['jenis'];
        $status = (string) $s['status'];
        $ulang  = $status === 'disetujui' && SuratSekolah::perluCetakUlang($s, $m['siswa'], $this->p);

        $data = $this->ringkas($s + ['jml_siswa' => count($m['siswa'])], $ulang ? [(int) $s['id'] => true] : []);
        $data += [
            'pengajuan_id' => ! empty($s['pengajuan_id']) ? (int) $s['pengajuan_id'] : null,
            'isi'          => array_diff_key((array) $s['isi_arr'], array_flip(SuratJenis::ISI_TERSEMBUNYI)),
            'ringkas_isi'  => array_map(static fn (array $x): array => ['label' => $x[0], 'nilai' => $x[1]], SuratJenis::ringkasIsi((array) $s['isi_arr'])),
            'siswa'        => array_map(static fn (array $x): array => [
                'siswa_id' => (int) $x['siswa_id'], 'nama' => $x['nama'], 'nis' => $x['nis'], 'nisn' => $x['nisn'],
                'kelas' => $x['nama_kelas'], 'jurusan' => $x['jurusan_nama'], 'hp' => ($x['hp'] ?? '') !== '' ? $x['hp'] : ($x['hp_master'] ?: null),
            ], $m['siswa']),
            'peringatan'   => in_array($status, ['menunggu', 'disetujui'], true) ? SuratPeriksa::untuk($s, $m['siswa'], $this->p) : [],
            'template_ada' => is_file(SuratJenis::pathTemplate($jenis, SuratJenis::varian($jenis, (array) $s['isi_arr']))),
            'riwayat'      => array_map(static fn (array $r): array => [
                'aksi' => $r['aksi'], 'label' => SuratSekolah::AKSI[$r['aksi']] ?? $r['aksi'], 'oleh' => $r['oleh'], 'peran' => $r['peran'],
                'catatan' => $r['catatan'], 'waktu' => $r['created_at'],
            ], $m['riwayat']),
            'hak'          => $this->hakSurat($s),
        ];
        $data['ada_bahaya'] = in_array('bahaya', array_column($data['peringatan'], 'tingkat'), true);

        return $this->ok($data, 'Detail surat ' . SuratSekolahModel::kode((int) $s['id']) . '.');
    }

    // ================================================================= keputusan

    public function acc($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'acc');
    }

    public function kembalikan($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'kembalikan');
    }

    public function batalAcc($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'batal_acc');
    }

    public function ajukanUlang($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'ajukan_ulang');
    }

    public function batal($id): ResponseInterface
    {
        return $this->putuskan((int) $id, 'batal');
    }

    /** Satu pintu keputusan; aturan (hak, Admin "mewakili", status asal, alasan wajib, peringatan bahaya) di SuratKeputusan. */
    private function putuskan(int $id, string $aksi): ResponseInterface
    {
        $hasil = (new SuratKeputusan($this->svc))->putuskan($id, $aksi, $this->body(), $this->konteks(), $this->p);
        if (! $hasil['ok']) {
            return $this->gagalKeputusan($hasil);
        }

        return $this->ok(['id' => $id, 'kode' => SuratSekolahModel::kode($id), 'status' => (string) ($this->svc->muat($id)['surat']['status'] ?? '')], $hasil['pesan']);
    }

    public function accMassal(): ResponseInterface
    {
        $hasil = (new SuratKeputusan($this->svc))->accMassal($this->body(), $this->konteks(), $this->p);
        if (! $hasil['ok'] && ($hasil['http'] ?? 200) !== 200) {
            return $this->gagalKeputusan(array_merge($hasil, ['kode' => $hasil['kode'] === 'catatan' ? 'ids' : $hasil['kode']]));
        }

        return $this->ok(['disetujui' => $hasil['disetujui'] ?? 0, 'dilewati' => $hasil['dilewati'] ?? []], $hasil['pesan']);
    }

    // ================================================================= unduhan

    public function unduh($id)
    {
        if (($tolak = $this->tolakKelola()) !== null) {
            return $tolak;
        }

        return $this->kirimBerkas((new SuratBerkas($this->svc))->bangun([(int) $id], $this->konteks()));
    }

    public function unduhMassal()
    {
        if (($tolak = $this->tolakKelola()) !== null) {
            return $tolak;
        }
        $in    = $this->body();
        $pilih = $this->svc->pilihUntukUnduh((string) ($in['mode'] ?? ''), (array) ($in['ids'] ?? []), (string) ($in['jenis'] ?? ''), SuratBerkas::MAKS_UNDUH, $this->p);
        if (! $pilih['ok']) {
            return $this->invalid(['mode' => $pilih['pesan']], $pilih['pesan']);
        }
        if ($pilih['ids'] === []) {
            return $this->ok(['jumlah' => 0], $pilih['pesan']);
        }

        return $this->kirimBerkas((new SuratBerkas($this->svc))->bangun($pilih['ids'], $this->konteks()));
    }

    /** Berkas Word (.docx) atau ZIP; header X-Surat-Ids = id surat di dalamnya (nomor bisa dibaca ulang lewat detail). */
    private function kirimBerkas(array $hasil)
    {
        if (! $hasil['ok']) {
            return $this->failure($hasil['pesan'], ($hasil['kode'] ?? '') === 'tidak_ada' ? 404 : 409, ['kode' => $hasil['kode'] ?? 'gagal']);
        }

        return $this->response->download($hasil['nama'], $hasil['biner'])->setFileName($hasil['nama'])
            ->setHeader('X-Jumlah-Surat', (string) $hasil['jumlah'])
            ->setHeader('X-Surat-Ids', implode(',', $hasil['ids']))
            ->setHeader('X-Zip', $hasil['zip'] ? '1' : '0');
    }

    // ================================================================= formulir & membuat

    /** GET surat/form/{slug} — pilihan & isian awal formulir. ASTS/TKA: ?tgl_mulai=&tgl_selesai= menandai perusahaan di luar periode PKL. */
    public function form(string $slug = ''): ResponseInterface
    {
        $jenis = self::jenisDariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis surat tidak dikenal (asts, tka, pernyataan-ortu, balasan, penarikan).');
        }
        if (($tolak = $this->tolakKelola()) !== null) {
            return $tolak;
        }
        $dasar = ['jenis' => $jenis, 'slug' => $slug, 'label' => SuratJenis::label($jenis), 'perlu_acc' => SuratJenis::perluAcc($jenis, $this->p)];

        if (SuratAcara::acara($jenis)) {
            $acara   = new SuratAcara();
            $periode = $jenis === SuratJenis::ASTS ? $acara->periodeUjian() : [];
            $mulai   = IsianBantu::tanggal((string) $this->request->getGet('tgl_mulai'), 2000, 2100);
            $selesai = IsianBantu::tanggal((string) $this->request->getGet('tgl_selesai'), 2000, 2100);

            return $this->ok($dasar + [
                'nilai_awal'    => SuratAcara::nilaiAwal($jenis, $periode, (string) ((new SettingModel())->get()['academic_year'] ?? '')),
                'periode_ujian' => $periode,
                'sesi_saran'    => SuratAcara::SESI_SARAN,
                'tempat_bawaan' => SuratAcara::TEMPAT_BAWAAN,
                'perusahaan'    => array_map(static fn (array $g): array => [
                    'kunci' => $g['kunci'], 'nama' => $g['nama'], 'alamat' => $g['alamat'], 'kota' => $g['kota'], 'mulai' => $g['mulai'], 'selesai' => $g['selesai'],
                    'luar_periode' => $g['luar_periode'], 'jml_siswa' => count($g['siswa']),
                    'siswa' => array_map(static fn (array $x): array => ['siswa_id' => $x['siswa_id'], 'nama' => $x['nama'], 'kelas' => $x['kelas']], $g['siswa']),
                ], $acara->perusahaan($mulai, $selesai)),
            ], 'Formulir ' . SuratJenis::label($jenis) . '.');
        }

        if ($jenis === SuratJenis::PERNYATAAN) {
            $lib = new SuratPernyataan();

            return $this->ok($dasar + [
                'nilai_awal' => ['tanggal_surat' => date('Y-m-d'), 'cakupan' => 'kelas', 'isi_ortu' => true],
                'cakupan'    => SuratPernyataan::CAKUPAN,
                'kelas'      => $lib->kelas($this->p),
                'ajuan'      => array_map(static fn (array $a): array => $a + ['jml_siswa' => count($a['siswa'])], $lib->ajuan()),
            ], 'Formulir ' . SuratJenis::label($jenis) . '. Siswa per kelas: GET surat/siswa?kelas_id[]=.');
        }

        return $this->ok($dasar + [
            'nilai_awal'    => ['tanggal_surat' => date('Y-m-d'), 'sapaan' => SuratPkl::SAPAAN_BAWAAN, 'periode_mulai' => '', 'periode_selesai' => '',
                'tgl_efektif' => date('Y-m-d'), 'alasan_pilih' => '0', 'alasan_lain' => ''],
            'ajuan'         => (new SuratPkl())->ajuan(),
            'sapaan_saran'  => SuratPkl::SAPAAN_SARAN,
            'alasan_saran'  => SuratPkl::ALASAN_SARAN,
            'batas_pkl'     => [
                'mulai_paling_awal' => ($this->p['mulai_paling_awal'] ?? '') ?: null, 'selesai_paling_akhir' => ($this->p['selesai_paling_akhir'] ?? '') ?: null,
                'durasi_min_hari' => (int) ($this->p['durasi_min_hari'] ?? 0), 'durasi_maks_hari' => (int) ($this->p['durasi_maks_hari'] ?? 0),
            ],
        ], 'Formulir ' . SuratJenis::label($jenis) . '.');
    }

    /** GET surat/siswa?kelas_id[]= — siswa aktif kelas-kelas itu (hanya kelas yang boleh PKL), untuk Pernyataan "siswa pilihan". */
    public function siswa(): ResponseInterface
    {
        if (($tolak = $this->tolakKelola()) !== null) {
            return $tolak;
        }
        $lib   = new SuratPernyataan();
        $boleh = array_column($lib->kelas($this->p), 'id');
        $minta = array_map('intval', (array) ($this->request->getGet('kelas_id') ?? []));
        $ids   = array_values(array_intersect($minta, $boleh));
        if ($ids === []) {
            return $this->invalid(['kelas_id' => 'Pilih minimal satu kelas yang boleh PKL (lihat surat/form/pernyataan-ortu).']);
        }

        return $this->ok($lib->siswaKelas($ids), 'Siswa aktif ' . count($ids) . ' kelas.');
    }

    /** POST surat/{slug} — buat surat (satu atau beberapa sekaligus, mis. per perusahaan / per kelas / per ajuan). */
    public function buat(string $slug = ''): ResponseInterface
    {
        $jenis = self::jenisDariSlug($slug);
        if ($jenis === null) {
            return $this->missing('Jenis surat tidak dikenal (asts, tka, pernyataan-ortu, balasan, penarikan).');
        }
        if (($tolak = $this->tolakKelola()) !== null) {
            return $tolak;
        }
        $in = $this->body();

        if (SuratAcara::acara($jenis)) {
            $h = SuratAcara::periksa($jenis, $in);
            if (! $h['ok']) {
                return $this->invalid(['galat' => $h['galat']], $h['galat'][0]);
            }
            $hasil = (new SuratAcara())->buat($jenis, $h, $this->konteks(), $this->p);
        } elseif ($jenis === SuratJenis::PERNYATAAN) {
            $h = SuratPernyataan::periksa($in);
            if (! $h['ok']) {
                return $this->invalid(['galat' => $h['galat']], $h['galat'][0]);
            }
            $hasil = (new SuratPernyataan())->buat($h, $this->konteks(), $this->p);
        } else {
            $h = SuratPkl::periksa($jenis, $in);
            if (! $h['ok']) {
                return $this->invalid(['galat' => $h['galat']], $h['galat'][0]);
            }
            $hasil = (new SuratPkl())->buat($jenis, $h, (array) ($in['ajuan'] ?? []), (array) ($in['siswa'] ?? []), $this->konteks(), $this->p);
        }

        $perluAcc = SuratJenis::perluAcc($jenis, $this->p);
        $data     = [
            'dibuat'    => array_map(static fn (int $id): array => ['id' => $id, 'kode' => SuratSekolahModel::kode($id)], array_map('intval', $hasil['dibuat'])),
            'sama'      => $hasil['sama'],
            'gagal'     => $hasil['gagal'],
            'perlu_acc' => $perluAcc,
        ];
        if (! $hasil['ok']) {
            return $this->failure('Tidak ada surat yang dibuat. ' . implode(' ', array_merge($hasil['sama'], $hasil['gagal'])), 409, $data);
        }

        return $this->created($data, $hasil['pesan'] . ($perluAcc ? ' Surat menunggu ACC Waka Hubin.' : ' Surat langsung siap diunduh.'));
    }

    /** POST surat/{id}/ubah — ubah data surat yang belum bernomor (status menunggu/dikembalikan). Pernyataan tidak diubah: batalkan lalu buat lagi. */
    public function ubah($id): ResponseInterface
    {
        if (($tolak = $this->tolakKelola()) !== null) {
            return $tolak;
        }
        $id = (int) $id;
        $m  = $this->svc->muat($id);
        if ($m === null) {
            return $this->missing('Surat tidak ditemukan.');
        }
        $s     = $m['surat'];
        $jenis = (string) $s['jenis'];
        if ($jenis === SuratJenis::PERNYATAAN || ! SuratJenis::siap($jenis)) {
            return $this->failure('Data surat jenis ini tidak bisa diubah. Batalkan surat lalu buat yang baru.', 409, ['kode' => 'jenis']);
        }
        if (($alasan = SuratSekolah::alasanTakBisaUbah($s)) !== null) {
            return $this->failure($alasan, 409, ['kode' => 'status']);
        }
        $in = $this->body();

        if (SuratAcara::acara($jenis)) {
            $r = (new SuratAcara())->ubahSurat($m, $in, $this->konteks());
            if ($r['kode'] === 'galat') {
                return $this->invalid(['galat' => $r['galat']], $r['galat'][0]);
            }
        } else {
            $h = SuratPkl::periksa($jenis, $in);
            if (! $h['ok']) {
                return $this->invalid(['galat' => $h['galat']], $h['galat'][0]);
            }
            $r = (new SuratPkl())->ubahSurat($m, $h, (array) ($in['siswa'] ?? []), $this->konteks());
        }
        if (! $r['ok']) {
            return $this->failure((string) ($r['pesan'] ?? 'Perubahan gagal disimpan.'), 409, ['kode' => (string) ($r['kode'] ?? 'gagal')]);
        }

        return $this->ok(['id' => $id, 'kode' => SuratSekolahModel::kode($id), 'status' => (string) $s['status']],
            'Data surat diperbarui.' . ($s['status'] === 'dikembalikan' ? ' Surat masih berstatus Dikembalikan: ajukan ulang untuk ACC bila sudah siap.' : ''));
    }

    // ================================================================= pembantu

    /**
     * Body JSON/form dengan nilai boolean dijadikan '1'/'0' — library membaca centang formulir web
     * (mis. isi_ortu "0" = jangan isi data orang tua), sehingga `false` dari aplikasi tidak terbaca "ya".
     */
    protected function body(): array
    {
        return array_map(static fn ($v) => is_bool($v) ? ($v ? '1' : '0') : $v, parent::body());
    }

    private function peran(): string
    {
        return (string) ($this->admin()['role'] ?? '');
    }

    /** Konteks pencatat riwayat & Audit Log: siapa, perannya, dari IP mana, lewat aplikasi. */
    private function konteks(): array
    {
        $a = (array) $this->admin();

        return [
            'oleh'     => (string) ($a['full_name'] ?? 'Staf'),
            'admin_id' => ((int) ($a['id'] ?? 0)) ?: null,
            'peran'    => (string) ($a['role'] ?? ''),
            'ip'       => $this->request->getIPAddress(),
            'saluran'  => 'aplikasi',
        ];
    }

    /** 403 bila peran ini tak memegang hak 'surat_sekolah' (buat, ubah, unduh); null bila boleh. */
    private function tolakKelola(): ?ResponseInterface
    {
        if (HakAkses::bolehPkl($this->peran(), 'surat_sekolah')) {
            return null;
        }

        return $this->forbidden('Akun ' . HakAkses::label($this->peran()) . ' tidak punya hak mengelola surat sekolah. Admin bisa mengaturnya di PKL → Hak Akses.');
    }

    /** Hasil gagal SuratKeputusan → respons API sesuai kode HTTP-nya. */
    private function gagalKeputusan(array $hasil): ResponseInterface
    {
        $http = (int) ($hasil['http'] ?? 400);

        return match ($http) {
            404     => $this->missing($hasil['pesan']),
            403     => $this->forbidden($hasil['pesan']),
            422     => $this->invalid([(string) $hasil['kode'] => $hasil['pesan']], $hasil['pesan']),
            default => $this->failure($hasil['pesan'], $http, ['kode' => $hasil['kode']]),
        };
    }

    /** Hak peran ini (tidak bergantung pada satu surat). */
    private function hakUmum(): array
    {
        $peran = $this->peran();

        return [
            'peran'       => $peran,
            'peran_label' => HakAkses::label($peran),
            'kelola'      => HakAkses::bolehPkl($peran, 'surat_sekolah'), // buat, ubah, ajukan ulang, batalkan, unduh
            'acc'         => HakAkses::bolehPkl($peran, 'acc'),           // ACC, kembalikan, batal ACC, ACC massal
            'wajib_wakil' => $peran === Peran::ADMIN,
        ];
    }

    /** Hak peran ini atas SATU surat (tombol yang boleh tampil; server tetap memeriksa ulang). */
    private function hakSurat(array $s): array
    {
        $peran   = $this->peran();
        $status  = (string) $s['status'];
        $jenis   = (string) $s['jenis'];
        $kelola  = HakAkses::bolehPkl($peran, 'surat_sekolah');
        $acc     = HakAkses::bolehPkl($peran, 'acc');
        $terbit  = (string) ($s['nomor'] ?? '') !== '' || (int) $s['cetak_ke'] > 0;

        return [
            'acc'          => $acc && $status === 'menunggu',
            'kembalikan'   => $acc && $status === 'menunggu',
            'batal_acc'    => $acc && $status === 'disetujui' && (int) $s['perlu_acc'] === 1 && ! $terbit,
            'ajukan_ulang' => $kelola && $status === 'dikembalikan',
            'batal'        => $kelola && in_array($status, ['menunggu', 'dikembalikan', 'disetujui'], true),
            'ubah'         => $kelola && $jenis !== SuratJenis::PERNYATAAN && SuratJenis::siap($jenis) && SuratSekolah::alasanTakBisaUbah($s) === null,
            'unduh'        => $kelola && $status === 'disetujui',
            'wajib_wakil'  => $peran === Peran::ADMIN,
        ];
    }

    /** Baris daftar / kepala detail. @param array<int, bool> $ulang id => perlu cetak ulang */
    private function ringkas(array $r, array $ulang = []): array
    {
        $id = (int) $r['id'];

        return [
            'id'                => $id,
            'kode'              => SuratSekolahModel::kode($id),
            'jenis'             => (string) $r['jenis'],
            'jenis_label'       => SuratJenis::label((string) $r['jenis']),
            'status'            => (string) $r['status'],
            'status_label'      => SuratSekolahModel::TAMPIL_STATUS[$r['status']][0] ?? (string) $r['status'],
            'perlu_acc'         => (int) $r['perlu_acc'] === 1,
            'judul'             => (string) $r['judul'],
            'tanggal_surat'     => (string) $r['tanggal_surat'],
            'nomor'             => ($r['nomor'] ?? '') !== '' ? (string) $r['nomor'] : null,
            'perusahaan'        => ($r['perusahaan_nama'] ?? '') !== '' ? (string) $r['perusahaan_nama'] : null,
            'jml_siswa'         => (int) ($r['jml_siswa'] ?? 0),
            'diunduh_kali'      => (int) $r['cetak_ke'],
            'perlu_cetak_ulang' => ! empty($ulang[$id]),
            'catatan_staf'      => ($r['catatan_staf'] ?? '') !== '' ? (string) $r['catatan_staf'] : null,
            'acc'               => ! empty($r['acc_at']) ? [
                'nama' => $r['acc_nama'], 'peran' => $r['acc_peran'], 'mewakili' => $r['acc_peran'] === Peran::ADMIN, 'waktu' => $r['acc_at'], 'kode' => $r['acc_kode'],
            ] : null,
            'dibuat_oleh'       => ($r['dibuat_nama'] ?? '') !== '' ? (string) $r['dibuat_nama'] : null,
            'dibuat_pada'       => $r['created_at'],
            'diubah_pada'       => $r['updated_at'],
        ];
    }

    /** "asts" | "tka" | "pernyataan-ortu" | "balasan" | "penarikan" (= ujung alamat menu web). */
    private static function slug(string $kode): string
    {
        return basename(SuratJenis::alamat($kode));
    }

    private static function jenisDariSlug(string $slug): ?string
    {
        foreach (SuratJenis::kode() as $k) {
            if (self::slug($k) === $slug) {
                return $k;
            }
        }

        return null;
    }
}
