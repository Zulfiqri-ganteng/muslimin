<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Models\AdminModel;
use App\Models\ApiTokenModel;
use App\Models\AuditModel;
use App\Models\BiometricCredentialModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Kelola Akun Staf — tambah akun, ubah peran, reset sandi, nonaktifkan.
 * Hanya peran 'admin' yang bisa membuka halaman ini (Config\Peran).
 *
 * Pengaman kesalahan manusia:
 *   - sandi sementara dibuat ACAK oleh sistem (tak ada "12345678") dan hanya
 *     tampil SEKALI; akun baru/direset WAJIB menggantinya saat login pertama;
 *   - tak bisa menonaktifkan, mereset, atau mengubah peran akun sendiri
 *     (mencegah mengunci diri sendiri);
 *   - tak bisa menyisakan nol admin aktif (dikunci FOR UPDATE agar dua admin
 *     yang saling menonaktifkan di detik yang sama pun tak lolos);
 *   - email unik (tanpa beda huruf besar), username dibuat otomatis;
 *   - nonaktif/reset sandi memutus token aplikasi Android milik akun itu;
 *   - semua aksi masuk Audit Log (sandi TIDAK pernah ikut ditulis).
 */
class Akun extends BaseController
{
    /** Huruf & angka yang tak mudah tertukar saat dibaca (tanpa 0/O, 1/l/I). */
    private const HURUF_BESAR = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const HURUF_KECIL = 'abcdefghijkmnopqrstuvwxyz';
    private const ANGKA       = '23456789';

    private AdminModel $model;
    private AuditModel $audit;

    public function __construct()
    {
        $this->model = new AdminModel();
        $this->audit = new AuditModel();
    }

    public function index()
    {
        $rows = $this->model
            ->select('id, full_name, email, username, phone, role, aktif, wajib_ganti_sandi, last_login_at, created_at')
            ->orderBy('aktif', 'DESC')
            ->orderBy('full_name', 'ASC')
            ->findAll();

        $ringkas = ['total' => count($rows), 'aktif' => 0, 'peran' => []];
        foreach ($rows as $r) {
            if ((int) $r['aktif'] === 1) {
                $ringkas['aktif']++;
            }
            $ringkas['peran'][$r['role']] = ($ringkas['peran'][$r['role']] ?? 0) + 1;
        }

        return view('admin/akun/index', [
            'title'   => 'Kelola Akun',
            'rows'    => $rows,
            'ringkas' => $ringkas,
            'peran'   => HakAkses::semua(),
            'saya'    => (int) session('admin.id'),
            'baru'    => session('akun_baru'),  // sandi sementara (sekali tampil)
            'formLama' => session('akun_form'), // isian yang gagal disimpan → modal dibuka lagi
            'sekolah' => (new \App\Models\SettingModel())->get()['school_name'] ?? 'Sekolah',
        ]);
    }

    /** Tambah akun baru; sandi sementara dibuat sistem. */
    public function store(): RedirectResponse
    {
        $in    = $this->masukan();
        $galat = $this->periksa($in, 0);
        if ($galat !== []) {
            return $this->kembaliGalat($galat, $in, 0);
        }

        $sandi = $this->sandiAcak();
        $data  = [
            'full_name'         => $in['full_name'],
            'email'             => $in['email'],
            'username'          => $this->buatUsername($in['email']),
            'phone'             => $in['phone'] !== '' ? $in['phone'] : null,
            'role'              => $in['role'],
            'password'          => password_hash($sandi, PASSWORD_DEFAULT),
            'aktif'             => 1,
            'wajib_ganti_sandi' => 1,
        ];

        try {
            $id = $this->model->skipValidation(true)->insert($data, true);
        } catch (\Throwable $e) {
            $id = false;
            log_message('error', 'Tambah akun gagal: ' . $e->getMessage());
        }
        if (! $id) {
            // Hampir pasti tabrakan UNIQUE: dua admin menambah email yang sama di detik yang sama.
            return $this->kembaliGalat(['email' => 'Email ini sudah dipakai akun lain.'], $in, 0);
        }

        $this->audit->record('create', 'admins', (int) $id, 'Akun staf dibuat: ' . $in['full_name'] . ' <' . $in['email'] . '>, peran ' . HakAkses::label($in['role']));
        $this->tampilkanSandi('dibuat', $in, $sandi);

        return redirect()->to(site_url('admin/akun'))->with('success', 'Akun ' . $in['full_name'] . ' berhasil dibuat.');
    }

    /** Ubah nama, email, HP, peran. */
    public function update($id): RedirectResponse
    {
        $id     = (int) $id;
        $target = $this->model->find($id);
        if (! $target) {
            return $this->kembali('error', 'Akun tidak ditemukan.');
        }

        $in = $this->masukan();
        if ($id === $this->saya()) {
            $in['role'] = $target['role']; // peran sendiri tak bisa diubah
        }
        $galat = $this->periksa($in, $id);
        if ($galat !== []) {
            return $this->kembaliGalat($galat, $in, $id);
        }

        $data = [
            'full_name' => $in['full_name'],
            'email'     => $in['email'],
            'phone'     => $in['phone'] !== '' ? $in['phone'] : null,
            'role'      => $in['role'],
        ];

        $kehilanganAdmin = $target['role'] === 'admin' && (int) $target['aktif'] === 1 && $in['role'] !== 'admin';
        $hasil           = $this->simpanDenganJaga($id, $data, $kehilanganAdmin);
        if ($hasil !== true) {
            return $this->kembali('error', $hasil);
        }

        // Peran berubah → token aplikasi lama tak boleh ikut "hidup" saat peran kembali.
        if ($in['role'] !== $target['role']) {
            $this->putusPerangkat($id);
        }

        $rincian = [];
        if ($in['role'] !== $target['role']) {
            $rincian[] = 'peran ' . HakAkses::label($target['role']) . ' → ' . HakAkses::label($in['role']);
        }
        if ($in['email'] !== mb_strtolower((string) $target['email'])) {
            $rincian[] = 'email diubah';
        }
        $this->audit->record('update', 'admins', $id, 'Akun staf diubah: ' . $in['full_name'] . ($rincian ? ' (' . implode(', ', $rincian) . ')' : ''));

        return $this->kembali('success', 'Akun ' . $in['full_name'] . ' diperbarui.');
    }

    /** Buat sandi sementara baru (lupa sandi). */
    public function resetSandi($id): RedirectResponse
    {
        $id     = (int) $id;
        $target = $this->model->find($id);
        if (! $target) {
            return $this->kembali('error', 'Akun tidak ditemukan.');
        }
        if ($id === $this->saya()) {
            return $this->kembali('error', 'Untuk mengganti sandi Anda sendiri, pakai menu Profil Saya.');
        }

        $sandi = $this->sandiAcak();
        $this->model->skipValidation(true)->update($id, [
            'password'          => password_hash($sandi, PASSWORD_DEFAULT),
            'wajib_ganti_sandi' => 1,
        ]);
        $this->putusPerangkat($id); // sandi direset = anggap perangkat lama tak tepercaya

        $this->audit->record('update', 'admins', $id, 'Sandi akun direset: ' . $target['full_name'] . ' <' . $target['email'] . '>');
        $this->tampilkanSandi('direset', [
            'full_name' => $target['full_name'],
            'email'     => $target['email'],
            'phone'     => (string) ($target['phone'] ?? ''),
            'role'      => $target['role'],
        ], $sandi);

        return redirect()->to(site_url('admin/akun'))->with('success', 'Sandi sementara untuk ' . $target['full_name'] . ' dibuat.');
    }

    /** Aktifkan / nonaktifkan akun (tanpa menghapus riwayatnya). */
    public function status($id): RedirectResponse
    {
        $id     = (int) $id;
        $target = $this->model->find($id);
        if (! $target) {
            return $this->kembali('error', 'Akun tidak ditemukan.');
        }
        if ($id === $this->saya()) {
            return $this->kembali('error', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');
        }

        $aktif = (int) $this->request->getPost('aktif') === 1 ? 1 : 0;
        if ($aktif === (int) $target['aktif']) {
            return $this->kembali('success', 'Status akun sudah ' . ($aktif ? 'aktif' : 'nonaktif') . '.');
        }

        $kehilanganAdmin = $aktif === 0 && $target['role'] === 'admin';
        $hasil           = $this->simpanDenganJaga($id, ['aktif' => $aktif], $kehilanganAdmin);
        if ($hasil !== true) {
            return $this->kembali('error', $hasil);
        }
        if ($aktif === 0) {
            $this->putusPerangkat($id);
        }

        $this->audit->record('update', 'admins', $id, 'Akun ' . ($aktif ? 'diaktifkan kembali' : 'dinonaktifkan') . ': ' . $target['full_name'] . ' <' . $target['email'] . '>');

        return $this->kembali('success', 'Akun ' . $target['full_name'] . ($aktif ? ' diaktifkan kembali.' : ' dinonaktifkan.'));
    }

    // =================================================================
    // Pembantu
    // =================================================================

    private function saya(): int
    {
        return (int) session('admin.id');
    }

    /** Ambil & rapikan isian form. */
    private function masukan(): array
    {
        return [
            'full_name' => trim((string) preg_replace('/\s+/u', ' ', (string) $this->request->getPost('full_name'))),
            'email'     => mb_strtolower(trim((string) $this->request->getPost('email'))),
            // Spasi, strip, titik, dan kurung boleh (0812-3456 7890); huruf TIDAK dibuang
            // diam-diam — dibiarkan agar validasi menolaknya.
            'phone'     => (string) preg_replace('/[\s().-]+/', '', (string) $this->request->getPost('phone')),
            'role'      => trim((string) $this->request->getPost('role')),
        ];
    }

    /**
     * @return array<string, string> galat per kolom (kosong = lolos)
     */
    private function periksa(array $in, int $id): array
    {
        $galat = [];

        $panjangNama = mb_strlen($in['full_name']);
        if ($panjangNama < 3 || $panjangNama > 150) {
            $galat['full_name'] = 'Nama lengkap harus 3–150 karakter.';
        }

        if ($in['email'] === '' || mb_strlen($in['email']) > 150 || ! filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $galat['email'] = 'Email tidak valid. Periksa penulisannya (contoh: nama@sekolah.sch.id).';
        } elseif ($this->model->where('email', $in['email'])->where('id !=', $id)->countAllResults() > 0) {
            $galat['email'] = 'Email ini sudah dipakai akun lain.';
        }

        if ($in['phone'] !== '' && ! preg_match('/^\+?[0-9]{8,15}$/', $in['phone'])) {
            $galat['phone'] = 'Nomor HP tidak valid (8–15 angka).';
        }

        if (! HakAkses::dikenal($in['role'])) {
            $galat['role'] = 'Pilih salah satu peran yang tersedia.';
        }

        return $galat;
    }

    /**
     * Simpan perubahan. Bila perubahan ini membuat sebuah admin aktif "hilang"
     * (dinonaktifkan / diturunkan perannya), pastikan masih ada admin aktif LAIN —
     * diperiksa di dalam transaksi dengan baris dikunci.
     *
     * @return true|string true bila tersimpan, selain itu pesan galat
     */
    private function simpanDenganJaga(int $id, array $data, bool $kehilanganAdmin)
    {
        $db = db_connect();
        $db->transStart();

        if ($kehilanganAdmin) {
            // Kunci semua admin aktif: transaksi kedua menunggu sampai ini selesai.
            $db->query("SELECT id FROM admins WHERE role = 'admin' AND aktif = 1 FOR UPDATE");
            if ($this->model->adminAktifSelain($id) < 1) {
                $db->transComplete();

                return 'Ditolak: ini admin aktif terakhir. Sistem harus selalu punya minimal satu admin aktif.';
            }
        }

        try {
            $this->model->skipValidation(true)->update($id, $data);
        } catch (\Throwable $e) {
            log_message('error', 'Ubah akun gagal: ' . $e->getMessage());
            $db->transComplete();

            return 'Perubahan gagal disimpan. Periksa email (mungkin sudah dipakai akun lain).';
        }

        $db->transComplete();

        return $db->transStatus() ? true : 'Perubahan gagal disimpan. Coba lagi.';
    }

    /** Cabut semua token aplikasi & sidik jari milik akun. */
    private function putusPerangkat(int $id): void
    {
        (new ApiTokenModel())->revokeAllFor($id);
        (new BiometricCredentialModel())->disableAllFor($id);
    }

    /** Username otomatis dari bagian depan email; selalu unik & minimal 4 karakter. */
    private function buatUsername(string $email): string
    {
        $dasar = strtolower((string) preg_replace('/[^a-z0-9._-]/i', '', explode('@', $email)[0]));
        $dasar = substr($dasar, 0, 90);
        if (strlen($dasar) < 4) {
            $dasar = str_pad($dasar, 4, '0');
        }

        $kandidat = $dasar;
        for ($n = 2; $this->model->where('username', $kandidat)->countAllResults() > 0; $n++) {
            $kandidat = $dasar . $n;
        }

        return $kandidat;
    }

    /** 10 karakter acak: pasti ada huruf besar, kecil, dan angka; tanpa karakter yang mirip. */
    private function sandiAcak(int $panjang = 10): string
    {
        $semua = self::HURUF_BESAR . self::HURUF_KECIL . self::ANGKA;
        $pilih = static fn (string $set): string => $set[random_int(0, strlen($set) - 1)];

        $huruf = [$pilih(self::HURUF_BESAR), $pilih(self::HURUF_KECIL), $pilih(self::ANGKA)];
        while (count($huruf) < $panjang) {
            $huruf[] = $pilih($semua);
        }
        for ($i = count($huruf) - 1; $i > 0; $i--) { // kocok (Fisher–Yates, acak aman)
            $j = random_int(0, $i);
            [$huruf[$i], $huruf[$j]] = [$huruf[$j], $huruf[$i]];
        }

        return implode('', $huruf);
    }

    /** Sandi sementara disimpan sebagai flash: tampil SEKALI di halaman berikutnya. */
    private function tampilkanSandi(string $jenis, array $in, string $sandi): void
    {
        session()->setFlashdata('akun_baru', [
            'jenis' => $jenis,
            'nama'  => $in['full_name'],
            'email' => $in['email'],
            'phone' => $in['phone'] ?? '',
            'peran' => HakAkses::label($in['role']),
            'sandi' => $sandi,
        ]);
    }

    private function kembaliGalat(array $galat, array $in, int $id): RedirectResponse
    {
        return redirect()->to(site_url('admin/akun'))
            ->with('errors', array_values($galat))
            ->with('akun_form', ['id' => $id] + $in);
    }

    private function kembali(string $jenis, string $pesan): RedirectResponse
    {
        return redirect()->to(site_url('admin/akun'))->with($jenis, $pesan);
    }
}
