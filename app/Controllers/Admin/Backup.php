<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\BackupSistem;
use App\Libraries\ModeMaintenance;
use App\Libraries\PulihkanBackup;
use App\Models\AdminModel;
use App\Models\AuditModel;

/**
 * Sistem → Backup & Restore (khusus Admin — MenuAdmin entri 'backup'; docs/DESAIN-ALAT-SISTEM.md F3 & F4).
 * Unduh (isi = data pribadi) dan restore wajib ketik ulang sandi; restore juga wajib Mode Maintenance aktif + ketik PULIHKAN.
 */
class Backup extends BaseController
{
    public function index()
    {
        $daftar   = BackupSistem::daftar();
        $otomatis = array_values(array_filter($daftar, static fn (array $b): bool => $b['jenis'] === 'otomatis'));

        return view('admin/sistem/backup', [
            'title'      => 'Backup & Restore',
            'daftar'     => $daftar,
            'total'      => array_sum(array_column($daftar, 'ukuran')),
            'otomatis'   => $otomatis[0] ?? null,
            'maintenance'=> ModeMaintenance::aktif(),
        ]);
    }

    /** POST admin/sistem/backup/buat — isi = db | berkas. */
    public function buat()
    {
        $isi = (string) $this->request->getPost('isi') === 'berkas' ? 'berkas' : 'db';
        try {
            $b = $isi === 'db' ? BackupSistem::buatDb('manual', $this->oleh()) : BackupSistem::buatBerkas('manual', $this->oleh());
        } catch (\Throwable $e) {
            log_message('error', '[Backup] gagal: ' . $e->getMessage());

            return $this->kembali('error', 'Backup gagal dibuat: ' . $e->getMessage());
        }
        (new AuditModel())->record('create', 'backup', null, 'Backup ' . ($isi === 'db' ? 'database' : 'berkas unggahan') . ' dibuat: ' . $b['kunci'] . ' (' . self::ukuran($b['ukuran']) . ')');

        return $this->kembali('success', 'Backup ' . ($isi === 'db' ? 'database' : 'berkas unggahan') . ' berhasil dibuat (' . self::ukuran($b['ukuran']) . ').');
    }

    /** GET admin/sistem/backup/unduh?kunci= — minta sandi dulu. */
    public function unduhForm()
    {
        $b = $this->cari((string) $this->request->getGet('kunci'));

        return $b === null ? $this->kembali('error', 'Backup tidak ditemukan.') : view('admin/sistem/backup_konfirmasi', ['title' => 'Unduh backup', 'mode' => 'unduh', 'b' => $b, 'maintenance' => ModeMaintenance::aktif()]);
    }

    /** POST admin/sistem/backup/unduh — kunci + sandi → berkas. */
    public function unduh()
    {
        $kunci = (string) $this->request->getPost('kunci');
        $path  = BackupSistem::path($kunci);
        if ($path === null) {
            return $this->kembali('error', 'Backup tidak ditemukan.');
        }
        if (! $this->sandiBenar((string) $this->request->getPost('sandi'))) {
            return redirect()->to(site_url('admin/sistem/backup/unduh') . '?kunci=' . rawurlencode($kunci))->with('error', 'Sandi salah.');
        }
        (new AuditModel())->record('update', 'backup', null, 'Backup diunduh: ' . $kunci);

        return $this->response->download($path, null)->setFileName($kunci);
    }

    /** POST admin/sistem/backup/hapus */
    public function hapus()
    {
        $kunci = (string) $this->request->getPost('kunci');
        if (! BackupSistem::hapus($kunci)) {
            return $this->kembali('error', 'Backup tidak ditemukan.');
        }
        (new AuditModel())->record('delete', 'backup', null, 'Backup dihapus: ' . $kunci);

        return $this->kembali('success', 'Backup dihapus.');
    }

    /** GET admin/sistem/backup/pulihkan?kunci= — halaman konfirmasi restore dari backup di daftar. */
    public function pulihkanForm()
    {
        $b = $this->cari((string) $this->request->getGet('kunci'));
        if ($b === null || $b['isi'] !== 'db') {
            return $this->kembali('error', 'Pilih backup database yang akan dipulihkan.');
        }

        return view('admin/sistem/backup_konfirmasi', ['title' => 'Pulihkan backup', 'mode' => 'pulihkan', 'b' => $b, 'maintenance' => ModeMaintenance::aktif()]);
    }

    /** POST admin/sistem/backup/pulihkan — dari backup di daftar (kunci) ATAU berkas unggahan (berkas). */
    public function pulihkan()
    {
        $kunci  = (string) $this->request->getPost('kunci');
        $balik  = $kunci !== '' ? site_url('admin/sistem/backup/pulihkan') . '?kunci=' . rawurlencode($kunci) : site_url('admin/sistem/backup') . '#pulihkan-unggah';
        $syarat = $this->syaratPulihkan();
        if ($syarat !== null) {
            return redirect()->to($balik)->with('error', $syarat);
        }

        if ($kunci !== '') {
            $path = BackupSistem::path($kunci);
            if ($path === null || ! str_starts_with($kunci, 'db-')) {
                return $this->kembali('error', 'Backup tidak ditemukan.');
            }
            $t = PulihkanBackup::mulai($path, $kunci, $this->oleh());
        } else {
            $f = $this->request->getFile('berkas');
            if ($f === null || ! $f->isValid()) {
                return redirect()->to($balik)->with('error', 'Pilih berkas .sql atau .sql.gz yang akan dipulihkan' . ($f !== null && $f->getError() !== UPLOAD_ERR_NO_FILE ? ' (' . $f->getErrorString() . ')' : '') . '.');
            }
            $nama = (string) $f->getClientName();
            if (! preg_match('/\.(sql|sql\.gz)$/i', $nama)) {
                return redirect()->to($balik)->with('error', 'Berkas harus .sql atau .sql.gz (ekspor phpMyAdmin atau backup dari halaman ini).');
            }
            $t = PulihkanBackup::mulai($f->getTempName(), $nama, $this->oleh());
        }
        if (isset($t['galat'])) {
            return redirect()->to($balik)->with('error', $t['galat']);
        }

        return redirect()->to(site_url('admin/sistem/backup/pulihkan/' . $t['id']));
    }

    /** GET admin/sistem/backup/pulihkan/{id} — kemajuan (pulihkan.js menjalankan langkah sampai selesai). */
    public function kemajuan(string $id)
    {
        $t = PulihkanBackup::tugas($id);

        return $t === null ? $this->kembali('error', 'Tugas restore tidak ditemukan.')
            : view('admin/sistem/backup_pulihkan', ['title' => 'Pulihkan backup', 't' => $t, 'persen' => PulihkanBackup::persen($t), 'maintenance' => ModeMaintenance::aktif()]);
    }

    /** POST admin/sistem/backup/pulihkan/{id}/langkah — JSON kemajuan. */
    public function langkah(string $id)
    {
        $t = PulihkanBackup::tugas($id);
        if ($t === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'pesan' => 'Tugas restore tidak ditemukan.']);
        }
        if (in_array($t['tahap'], ['siap', 'jalan'], true) && ! ModeMaintenance::aktif()) {
            return $this->response->setJSON(['ok' => false, 'tahap' => $t['tahap'], 'persen' => PulihkanBackup::persen($t),
                'pesan' => 'Restore DIJEDA karena Mode Maintenance dimatikan. Aktifkan lagi lalu muat ulang halaman ini untuk melanjutkan.']);
        }
        $lama = $t['tahap'];
        $t    = PulihkanBackup::langkah($id);
        if ($lama !== $t['tahap'] && in_array($t['tahap'], ['selesai', 'gagal'], true)) {
            (new AuditModel())->record('update', 'backup', null, mb_substr('Restore ' . ($t['tahap'] === 'selesai' ? 'SELESAI' : 'GAGAL') . ' dari ' . $t['sumber']
                . ' oleh ' . $t['oleh'] . ($t['galat'] ? ' — ' . $t['galat'] : ' (' . $t['perintah'] . ' perintah)'), 0, 255));
        }

        return $this->response->setJSON(['ok' => true, 'tahap' => $t['tahap'], 'persen' => PulihkanBackup::persen($t), 'perintah' => $t['perintah'],
            'pesan' => self::teksTahap($t), 'alamat' => site_url('admin/sistem/backup/pulihkan/' . $id)]);
    }

    public static function teksTahap(array $t): string
    {
        return match ($t['tahap']) {
            'siap'    => 'Menyiapkan: membuat backup data sekarang sebelum dipulihkan…',
            'jalan'   => 'Memulihkan… ' . number_format($t['perintah'], 0, ',', '.') . ' perintah dijalankan.',
            'selesai' => 'Selesai. ' . number_format($t['perintah'], 0, ',', '.') . ' perintah dijalankan.',
            default   => 'Gagal: ' . (string) $t['galat'],
        };
    }

    public static function ukuran(int $b): string
    {
        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) ceil($b / 1024)) . ' KB';
    }

    // =================================================================

    /** Pesan galat bila syarat restore belum terpenuhi (maintenance, sandi, kata PULIHKAN), atau null. */
    private function syaratPulihkan(): ?string
    {
        if (! ModeMaintenance::aktif()) {
            return 'Aktifkan Mode Maintenance dulu (Sistem → Mode Maintenance) supaya tidak ada yang mengubah data selama restore.';
        }
        if (! $this->sandiBenar((string) $this->request->getPost('sandi'))) {
            return 'Sandi salah.';
        }
        if (strtoupper(trim((string) $this->request->getPost('kata'))) !== PulihkanBackup::KATA) {
            return 'Ketik ' . PulihkanBackup::KATA . ' (huruf besar) untuk memastikan.';
        }

        return null;
    }

    private function sandiBenar(string $sandi): bool
    {
        $a = (new AdminModel())->find((int) (((array) session('admin'))['id'] ?? 0));

        return $a !== null && $sandi !== '' && password_verify($sandi, (string) ($a['password'] ?? ''));
    }

    private function cari(string $kunci): ?array
    {
        foreach (BackupSistem::daftar() as $b) {
            if ($b['kunci'] === $kunci) {
                return $b;
            }
        }

        return null;
    }

    private function kembali(string $jenis, string $pesan)
    {
        return redirect()->to(site_url('admin/sistem/backup'))->with($jenis, $pesan);
    }

    private function oleh(): string
    {
        $a = (array) session('admin');

        return (string) (($a['full_name'] ?? '') ?: ($a['username'] ?? 'Admin'));
    }
}
