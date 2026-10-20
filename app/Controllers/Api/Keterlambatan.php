<?php

namespace App\Controllers\Api;

use App\Libraries\HakAkses;
use App\Libraries\HonorCetak;
use App\Libraries\Keterlambatan as Buku;
use App\Libraries\KeterlambatanExcel;
use App\Libraries\KeterlambatanWa;
use App\Libraries\PklWa;
use App\Models\AuditModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API Catat Keterlambatan siswa (menu Guru Piket) untuk aplikasi Android — cermin halaman web admin/keterlambatan dan
 * admin/keterlambatan/rekap. Kontrak lengkap: docs/API-KETERLAMBATAN.md.
 *
 * Controller tipis: SEMUA aturan dari library yang sama dengan web (Libraries\Keterlambatan, KeterlambatanExcel, KeterlambatanWa)
 * → hasil web = hasil aplikasi. Audit Log diberi "(via aplikasi)".
 * Gerbang: ApiAuthFilter (awalan 'keterlambatan' / 'keterlambatan/rekap' dari katalog menu, peran harus "boleh aplikasi") + hak per
 * aksi di sini dengan aturan web yang SAMA — HakAkses::boleh() memakai alamat web padanannya: melihat = GET admin/keterlambatan,
 * catat (termasuk siswa belum terdaftar)/ubah/hapus/kabari/isi nomor/tautkan = POST (level Kelola), rekap = menu Rekap Keterlambatan, templat pesan = khusus Admin.
 */
class Keterlambatan extends BaseApiController
{
    private const WEB = 'admin/keterlambatan';

    // ================================================================= daftar & meta

    /** GET keterlambatan/meta?tanggal= — bahan layar: guru piket hari itu, pilihan guru & kelas, saran sanksi, batas, hak, templat WA. */
    public function meta(): ResponseInterface
    {
        if (! $this->boleh(self::WEB) && ! $this->boleh(self::WEB . '/rekap')) {
            return $this->tolakHak('membuka Catat Keterlambatan');
        }
        $buku    = new Buku('aplikasi');
        $tanggal = Buku::tanggalSah((string) $this->request->getGet('tanggal')) ?? date('Y-m-d');

        return $this->ok([
            'hari_ini'     => date('Y-m-d'),
            'tanggal'      => $tanggal,
            'hari_tanggal' => Buku::hariTanggal($tanggal),
            'tahun_ajaran' => Buku::tahunAjaran(),
            'guru_piket'   => $buku->guruPiketHari($tanggal),
            'guru'         => $buku->pilihanGuru(),
            'kelas'        => $buku->pilihanKelas(),
            'saran_sanksi' => $buku->saranSanksi(),
            'batas'        => ['sanksi' => Buku::MAKS_SANKSI, 'keterangan' => Buku::MAKS_KETERANGAN, 'rentang_hari' => Buku::MAKS_HARI_RENTANG, 'cari_min' => 2, 'baris_excel' => KeterlambatanExcel::BARIS_MIN,
                'nama_min' => Buku::MIN_NAMA, 'nama' => Buku::MAKS_NAMA],
            'hak'          => $this->hak(),
            'wa'           => ['templat' => KeterlambatanWa::templat(), 'bawaan' => KeterlambatanWa::PESAN_BAWAAN, 'penanda' => KeterlambatanWa::TOKEN, 'maks' => PklWa::MAKS_PESAN],
        ], 'Bahan layar keterlambatan.');
    }

    /** GET keterlambatan?tanggal= — catatan satu tanggal (bawaan hari ini), urut jam datang. */
    public function daftar(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB, 'GET', 'melihat catatan keterlambatan')) !== null) {
            return $t;
        }
        $buku    = new Buku('aplikasi');
        $tanggal = Buku::tanggalSah((string) $this->request->getGet('tanggal')) ?? date('Y-m-d');
        [$templat, $sekolah] = $this->bahanWa();
        $baris = array_map(fn (array $r): array => $this->baris($r, $templat, $sekolah), $buku->daftar($tanggal));

        return $this->ok([
            'tanggal'      => $tanggal,
            'hari_tanggal' => Buku::hariTanggal($tanggal),
            'guru_piket'   => $buku->guruPiketHari($tanggal),
            'jumlah'       => count($baris),
            'baris'        => $baris,
        ], count($baris) . ' siswa terlambat pada ' . Buku::hariTanggal($tanggal) . '.');
    }

    /** GET keterlambatan/{id} */
    public function detail($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB, 'GET', 'melihat catatan keterlambatan')) !== null) {
            return $t;
        }
        $r = (new Buku('aplikasi'))->cariLengkap((int) $id);
        if ($r === null) {
            return $this->missing('Catatan keterlambatan tidak ditemukan (mungkin sudah dihapus).');
        }
        [$templat, $sekolah] = $this->bahanWa();

        return $this->ok($this->baris($r, $templat, $sekolah));
    }

    /**
     * GET keterlambatan/siswa?q=&tanggal=&manual=1 — siswa aktif yang cocok (≥ 2 huruf); 'tercatat' = sudah dicatat pada tanggal itu.
     * manual=1: + di akhir, siswa BELUM TERDAFTAR yang pernah dicatat manual (id null, manual true, jumlah, hp_ortu). Tanpa manual=1
     * isinya hanya siswa terdaftar (aplikasi versi lama tetap aman).
     * GET keterlambatan/siswa?kelas_id=&tanggal=&manual=1 — SEMUA siswa aktif kelas itu, urut nama, bentuk baris sama (q diabaikan;
     * manual=1: + siswa belum terdaftar kelas itu tahun ajaran ini). Kelas tidak ada → 404.
     */
    public function siswa(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB, 'GET', 'mencari siswa')) !== null) {
            return $t;
        }
        $buku    = new Buku('aplikasi');
        $tanggal = Buku::tanggalSah((string) $this->request->getGet('tanggal')) ?? date('Y-m-d');
        $manual  = Buku::mintaManual($this->request->getGet('manual'));
        $kelasId = (int) $this->request->getGet('kelas_id');
        if ($kelasId > 0) {
            $r = $buku->siswaKelas($kelasId, $tanggal, $manual);
            if ($r === null) {
                return $this->missing('Kelas tidak ditemukan (mungkin sudah dihapus). Muat ulang daftar kelas.');
            }
            $terdaftar = count(array_filter($r['hasil'], static fn (array $s): bool => ! $s['manual']));

            return $this->ok($r['hasil'], $terdaftar . ' siswa aktif di ' . $r['kelas']['nama'] . '.');
        }

        return $this->ok($buku->cariSiswa((string) $this->request->getGet('q'), $tanggal, 15, $manual));
    }

    // ================================================================= catat, ubah, hapus, tautkan

    /**
     * POST keterlambatan — {siswa_id, guru_id, tanggal? (bawaan hari ini), jam? "07:15", sanksi?, keterangan?} → 201 baris.
     * Siswa BELUM TERDAFTAR: {manual: true, nama_siswa, kelas_id, hp_ortu?, guru_id, …} (siswa_id diabaikan). Nama itu ternyata siswa
     * aktif terdaftar di kelas yang sama → 422 + data.siswa_terdaftar (aplikasi menawarkan "Pakai siswa ini").
     */
    public function catat(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB, 'POST', 'mencatat keterlambatan')) !== null) {
            return $t;
        }
        $in   = $this->body();
        $buku = new Buku('aplikasi');
        $r    = $buku->catat([
            'siswa_id'   => $in['siswa_id'] ?? null,
            'tanggal'    => (string) ($in['tanggal'] ?? '') !== '' ? $in['tanggal'] : date('Y-m-d'),
            'jam'        => $in['jam'] ?? null,
            'guru_id'    => $in['guru_id'] ?? null,
            'sanksi'     => $in['sanksi'] ?? null,
            'keterangan' => $in['keterangan'] ?? null,
            'manual'     => $in['manual'] ?? null,
            'nama_siswa' => $in['nama_siswa'] ?? null,
            'kelas_id'   => $in['kelas_id'] ?? null,
            'hp_ortu'    => $in['hp_ortu'] ?? null,
        ], $this->pencatat());
        if (! $r['ok']) {
            return $this->tolakManual($r);
        }
        [$templat, $sekolah] = $this->bahanWa();

        return $this->created($this->baris((array) $buku->cariLengkap((int) $r['id']), $templat, $sekolah), $r['pesan']);
    }

    /**
     * POST keterlambatan/{id}/ubah — {guru_id, jam?, sanksi?, keterangan?}; siswa terdaftar & tanggal tidak bisa diubah.
     * jam/sanksi/keterangan yang TIDAK dikirim = tetap (dulu ikut terhapus); "" = dikosongkan.
     * Catatan siswa belum terdaftar (baris.manual): + nama_siswa?, kelas_id?, hp_ortu? — kunci yang tidak dikirim = tidak diubah;
     * hp_ortu "" = dikosongkan.
     */
    public function ubah($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/' . (int) $id, 'POST', 'mengubah catatan keterlambatan')) !== null) {
            return $t;
        }
        $in   = $this->body();
        $buku = new Buku('aplikasi');
        $lama = $buku->cari((int) $id) ?? [];
        $data = ['guru_id' => $in['guru_id'] ?? null];
        foreach (['jam', 'sanksi', 'keterangan'] as $k) {
            $data[$k] = array_key_exists($k, $in) ? $in[$k] : ($lama[$k] ?? null);
        }
        foreach (['nama_siswa', 'kelas_id', 'hp_ortu'] as $k) {
            if (array_key_exists($k, $in)) {
                $data[$k] = $in[$k] ?? '';
            }
        }
        $r = $buku->ubah((int) $id, $data);
        if (! $r['ok']) {
            return $this->tolakManual($r);
        }
        [$templat, $sekolah] = $this->bahanWa();

        return $this->ok($this->baris((array) $buku->cariLengkap((int) $id), $templat, $sekolah), $r['pesan']);
    }

    /**
     * POST keterlambatan/{id}/tautkan — {siswa_id, semua?: bool (bawaan true)}: catatan siswa belum terdaftar → siswa di Data Siswa
     * (semua = juga catatan manual lain dengan nama + kelas sama). → {jumlah, ids[], dilewati[] (tanggal), baris (catatan ini)}.
     * Bukan catatan manual / siswa_id salah → 422; semua tanggal bentrok → 409.
     */
    public function tautkan($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/' . (int) $id . '/tautkan', 'POST', 'menautkan catatan ke Data Siswa')) !== null) {
            return $t;
        }
        $in   = $this->body();
        $buku = new Buku('aplikasi');
        $r    = $buku->tautkan((int) $id, (int) ($in['siswa_id'] ?? 0), ! array_key_exists('semua', $in) || Buku::mintaManual($in['semua']));
        if (! $r['ok']) {
            return $this->tolakHasil($r);
        }
        [$templat, $sekolah] = $this->bahanWa();

        return $this->ok([
            'jumlah'   => $r['jumlah'],
            'ids'      => $r['ids'],
            'dilewati' => $r['dilewati'],
            'baris'    => in_array((int) $id, $r['ids'], true) ? $this->baris((array) $buku->cariLengkap((int) $id), $templat, $sekolah) : null,
        ], $r['pesan']);
    }

    /** Galat catat/ubah: 422 "nama sudah terdaftar" membawa data.siswa_terdaftar; selain itu seperti tolakHasil(). */
    private function tolakManual(array $r): ResponseInterface
    {
        if (isset($r['siswa_terdaftar'])) {
            return $this->failure((string) $r['pesan'], 422, ['errors' => (array) ($r['errors'] ?? []), 'siswa_terdaftar' => $r['siswa_terdaftar']]);
        }

        return $this->tolakHasil($r);
    }

    /** DELETE keterlambatan/{id} */
    public function hapus($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/delete/' . (int) $id, 'POST', 'menghapus catatan keterlambatan')) !== null) {
            return $t;
        }
        $r = (new Buku('aplikasi'))->hapus((int) $id);

        return $r['ok'] ? $this->ok(['id' => (int) $id, 'tanggal' => $r['tanggal']], $r['pesan']) : $this->tolakHasil($r);
    }

    // ================================================================= kabar WhatsApp orang tua

    /** POST keterlambatan/{id}/kabari — dipanggil saat guru piket membuka tautan wa.me (baris.wa.url). */
    public function kabari($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/' . (int) $id . '/kabari', 'POST', 'mengabari orang tua')) !== null) {
            return $t;
        }
        $r = (new KeterlambatanWa())->tandai((int) $id, $this->pencatat()['nama'], 'aplikasi');

        return $r['ok'] ? $this->ok(['id' => (int) $id, 'dikabari_at' => $r['waktu'], 'dikabari_oleh' => $r['oleh']], 'Tercatat sudah dikabari.') : $this->tolakHasil($r);
    }

    /** POST keterlambatan/{id}/hp — {hp}: isi nomor HP orang tua ke Data Siswa (hanya kolom yang masih kosong) → baris terbaru (wa.url). */
    public function isiHp($id): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/' . (int) $id . '/hp', 'POST', 'mengisi nomor HP orang tua')) !== null) {
            return $t;
        }
        $r = (new KeterlambatanWa())->isiHp((int) $id, (string) ($this->body()['hp'] ?? ''), 'aplikasi');
        if (! $r['ok']) {
            return $this->tolakHasil($r);
        }
        [$templat, $sekolah] = $this->bahanWa();

        return $this->ok($this->baris((array) (new Buku('aplikasi'))->cariLengkap((int) $id), $templat, $sekolah), $r['pesan']);
    }

    /** POST keterlambatan/pesan — {wa_pesan} templat pesan WA (kosong = bawaan). KHUSUS Admin. */
    public function pesan(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/pesan', 'POST', 'mengubah pesan WhatsApp (khusus Admin)')) !== null) {
            return $t;
        }
        $galat = KeterlambatanWa::simpanTemplat((string) ($this->body()['wa_pesan'] ?? ''));
        if ($galat !== null) {
            return $this->invalid(['wa_pesan' => $galat], $galat);
        }

        return $this->ok(['templat' => KeterlambatanWa::templat(), 'bawaan' => KeterlambatanWa::templat() === KeterlambatanWa::PESAN_BAWAAN], 'Pesan WhatsApp ke orang tua disimpan.');
    }

    // ================================================================= Excel & rekap

    /** GET keterlambatan/excel?tanggal= — Excel buku piket hari itu (format sekolah, selalu satu lembar). */
    public function excel()
    {
        if (($t = $this->tolak(self::WEB . '/excel', 'GET', 'mengunduh buku piket')) !== null) {
            return $t;
        }
        $tanggal = Buku::tanggalSah((string) $this->request->getGet('tanggal')) ?? date('Y-m-d');
        $bahan   = (new Buku('aplikasi'))->bahanBuku($tanggal, $tanggal);
        (new AuditModel())->record('export', 'keterlambatan_siswa', null, 'Unduh Excel buku piket ' . $tanggal . ' (' . count($bahan[0]['rows']) . ' siswa) (via aplikasi)');

        return $this->berkas(KeterlambatanExcel::bukuPiket($bahan), KeterlambatanExcel::namaBerkasHari($tanggal));
    }

    /** GET keterlambatan/rekap?mulai=&selesai=&kelas_id= — rekap per siswa & per kelas (bawaan awal bulan s.d. hari ini). */
    public function rekap(): ResponseInterface
    {
        if (($t = $this->tolak(self::WEB . '/rekap', 'GET', 'membuka Rekap Keterlambatan')) !== null) {
            return $t;
        }
        $buku = new Buku('aplikasi');
        [$r, $kelasId] = $this->saringan($buku);
        $rekap = $buku->rekap($r['mulai'], $r['selesai'], $kelasId);

        return $this->ok([
            'mulai'    => $r['mulai'],
            'selesai'  => $r['selesai'],
            'kelas_id' => $kelasId ?: null,
            'ringkas'  => ['total' => $rekap['total'], 'jumlah_siswa' => $rekap['jumlah_siswa'], 'jumlah_hari' => $rekap['jumlah_hari']],
            'siswa'    => $rekap['siswa'],
            'kelas'    => $rekap['kelas'],
        ], $r['pesan'] ?? 'Rekap keterlambatan.');
    }

    /** GET keterlambatan/rekap/excel?… — Excel rekap per siswa & per kelas. */
    public function rekapExcel()
    {
        if (($t = $this->tolak(self::WEB . '/rekap/excel', 'GET', 'mengunduh rekap')) !== null) {
            return $t;
        }
        $buku = new Buku('aplikasi');
        [$r, $kelasId] = $this->saringan($buku);
        $rekap = $buku->rekap($r['mulai'], $r['selesai'], $kelasId);
        $nama  = '';
        foreach ($buku->pilihanKelas() as $k) {
            $nama = $k['id'] === $kelasId ? $k['nama'] : $nama;
        }
        (new AuditModel())->record('export', 'keterlambatan_siswa', null, 'Unduh rekap keterlambatan ' . $r['mulai'] . ' s.d. ' . $r['selesai'] . ' (' . $rekap['total'] . ' catatan) (via aplikasi)');

        return $this->berkas(KeterlambatanExcel::rekap($rekap, $r['mulai'], $r['selesai'], $nama), KeterlambatanExcel::namaBerkasRekap($r['mulai'], $r['selesai']));
    }

    /** GET keterlambatan/rekap/buku?… — Excel buku piket rentang (satu lembar per hari yang ada catatannya); kosong → 404 JSON. */
    public function rekapBuku()
    {
        if (($t = $this->tolak(self::WEB . '/rekap/buku', 'GET', 'mengunduh buku piket')) !== null) {
            return $t;
        }
        $buku = new Buku('aplikasi');
        [$r, $kelasId] = $this->saringan($buku);
        $bahan = $buku->bahanBuku($r['mulai'], $r['selesai'], $kelasId);
        if ($bahan === []) {
            return $this->missing('Tidak ada catatan keterlambatan pada rentang itu — tidak ada yang diunduh.');
        }
        (new AuditModel())->record('export', 'keterlambatan_siswa', null, 'Unduh Excel buku piket ' . $r['mulai'] . ' s.d. ' . $r['selesai'] . ' (' . count($bahan) . ' hari) (via aplikasi)');

        return $this->berkas(KeterlambatanExcel::bukuPiket($bahan), KeterlambatanExcel::namaBerkasRentang($r['mulai'], $r['selesai']));
    }

    // ================================================================= bantu

    /** Bentuk satu baris untuk aplikasi (+ bahan tombol WA). */
    private function baris(array $r, string $templat, string $sekolah): array
    {
        $k      = KeterlambatanWa::kabar($r, $templat, $sekolah);
        $manual = Buku::manual($r);

        return [
            'id'            => (int) $r['id'],
            'tanggal'       => (string) $r['tanggal'],
            'hari_tanggal'  => Buku::hariTanggal((string) $r['tanggal']),
            'jam'           => $r['jam'] !== null ? substr((string) $r['jam'], 0, 5) : null,
            'siswa_id'      => $r['siswa_id'] !== null ? (int) $r['siswa_id'] : null,
            'manual'        => $manual,
            'hp_ortu'       => $manual ? ($r['hp_ortu'] ?? null) : null,
            'nama_siswa'    => (string) $r['nama_siswa'],
            'nis'           => ((string) ($r['nis'] ?? '')) ?: null,
            'kelas_id'      => $r['kelas_id'] !== null ? (int) $r['kelas_id'] : null,
            'nama_kelas'    => $r['nama_kelas'] ?? null,
            'shift'         => $r['shift'] ?? null,
            'tahun_ajaran'  => $r['tahun_ajaran'] ?? null,
            'sanksi'        => $r['sanksi'] ?? null,
            'keterangan'    => $r['keterangan'] ?? null,
            'guru_id'       => $r['guru_id'] !== null ? (int) $r['guru_id'] : null,
            'guru_piket'    => $r['guru_piket'] ?? null,
            'dicatat_oleh'  => $r['dicatat_oleh'] ?? null,
            'dicatat_at'    => $r['created_at'] ?? null,
            'dikabari_at'   => $r['dikabari_at'] ?? null,
            'dikabari_oleh' => $r['dikabari_oleh'] ?? null,
            'wa'            => [
                'hp'           => $k['hp'],
                'sumber'       => $k['sumber'],
                'url'          => $k['url'],
                'pesan'        => $k['pesan'],
                'alasan_tidak' => $k['alasan_tidak'],
                'bisa_isi_hp'  => $k['url'] === null && KeterlambatanWa::bisaIsiHp($r),
            ],
        ];
    }

    /** @return array{0: string, 1: string} templat WA & nama sekolah */
    private function bahanWa(): array
    {
        return [KeterlambatanWa::templat(), (string) ((new SettingModel())->get()['school_name'] ?? '')];
    }

    /** @return array{0: array{mulai: string, selesai: string, pesan: ?string}, 1: int} rentang & kelas_id sah (0 = semua) */
    private function saringan(Buku $buku): array
    {
        $r  = Buku::rentangSah((string) $this->request->getGet('mulai'), (string) $this->request->getGet('selesai'));
        $id = (int) $this->request->getGet('kelas_id');

        return [$r, in_array($id, array_column($buku->pilihanKelas(), 'id'), true) ? $id : 0];
    }

    private function berkas(\PhpOffice\PhpSpreadsheet\Spreadsheet $ss, string $nama)
    {
        return $this->response->download($nama, HonorCetak::xlsx($ss))->setFileName($nama);
    }

    /** @return array<string, bool> hak peran ini (aplikasi menyembunyikan tombol yang tidak boleh) */
    private function hak(): array
    {
        $kelola = $this->boleh(self::WEB . '/0', 'POST');

        return [
            'lihat'      => $this->boleh(self::WEB),
            'catat'      => $this->boleh(self::WEB, 'POST'),
            'ubah'       => $kelola,
            'hapus'      => $this->boleh(self::WEB . '/delete/0', 'POST'),
            'kabari'     => $this->boleh(self::WEB . '/0/kabari', 'POST'),
            'isi_hp'     => $this->boleh(self::WEB . '/0/hp', 'POST'),
            'tautkan'    => $this->boleh(self::WEB . '/0/tautkan', 'POST'),
            'excel'      => $this->boleh(self::WEB . '/excel'),
            'rekap'      => $this->boleh(self::WEB . '/rekap'),
            'ubah_pesan' => $this->boleh(self::WEB . '/pesan', 'POST') && KeterlambatanWa::kolomAda(),
        ];
    }

    private function boleh(string $alamatWeb, string $metode = 'GET'): bool
    {
        return HakAkses::boleh((string) ($this->admin()['role'] ?? ''), $alamatWeb, $metode);
    }

    private function tolak(string $alamatWeb, string $metode, string $aksi): ?ResponseInterface
    {
        return $this->boleh($alamatWeb, $metode) ? null : $this->tolakHak($aksi);
    }

    private function tolakHak(string $aksi): ResponseInterface
    {
        return $this->forbidden('Akun ' . HakAkses::label((string) ($this->admin()['role'] ?? '')) . ' tidak punya hak ' . $aksi . '. Admin bisa mengaturnya di Sistem → Hak Akses.');
    }

    /** @return array{id: ?int, nama: string} */
    private function pencatat(): array
    {
        $a = (array) $this->admin();

        return ['id' => ((int) ($a['id'] ?? 0)) ?: null, 'nama' => (string) ($a['full_name'] ?? '')];
    }
}
