<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Libraries\ApiAuth;
use App\Libraries\BackupSistem;
use App\Libraries\LogGalat;
use App\Libraries\ModeMaintenance;
use App\Libraries\SesiAktif;
use App\Models\AuditModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * API Sistem — KHUSUS ADMIN (docs/API-SISTEM.md; kontrak Flutter BLUEPRINT-SISTEM.md). Operator & Waka Hubin sudah ditolak
 * penyaring apiauth (awalan 'sistem' tidak ada di api_akses mereka); controller memeriksa ulang peran Admin.
 *   GET  sistem/maintenance            status Mode Maintenance
 *   POST sistem/maintenance            {aktif: bool, pesan?, selesai?: "Y-m-d H:i"} nyalakan/perbarui/matikan
 *   GET  sistem/log-error              ?berkas=&tingkat=&q= daftar berkas log + isi berkas terpilih (galat sama digabung)
 *   POST sistem/log-error/bersihkan    hapus log aplikasi > 30 hari
 */
class Sistem extends BaseApiController
{
    public function maintenance(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }

        return $this->ok($this->dataMaintenance(), 'Status mode maintenance.');
    }

    public function simpanMaintenance(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $in = $this->body();
        if (! array_key_exists('aktif', $in)) {
            return $this->invalid(['aktif' => 'Wajib diisi: true untuk mengaktifkan/memperbarui, false untuk mematikan.']);
        }
        $r = ModeMaintenance::simpan(filter_var($in['aktif'], FILTER_VALIDATE_BOOLEAN), (string) ($in['pesan'] ?? ''),
            (string) ($in['selesai'] ?? ''), (string) (($this->admin()['full_name'] ?? '') ?: ($this->admin()['username'] ?? 'Admin')));

        return $r['ok'] ? $this->ok($this->dataMaintenance(), $r['pesan']) : $this->invalid(['selesai' => $r['pesan']], $r['pesan']);
    }

    public function logError(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $lib     = new LogGalat();
        $berkas  = $lib->berkas();
        $kunci   = (string) $this->request->getGet('berkas');
        $kunci   = isset($berkas[$kunci]) ? $kunci : (string) LogGalat::terbaru($berkas);
        $tingkat = (string) $this->request->getGet('tingkat');
        $tingkat = isset(LogGalat::TINGKAT[$tingkat]) ? $tingkat : '';
        $q       = trim((string) $this->request->getGet('q'));
        $isi     = $kunci !== '' ? $lib->baca($kunci) : null;
        $entri   = $isi !== null ? LogGalat::saring($isi['entri'], $tingkat, $q) : [];

        return $this->ok([
            'berkas' => array_values(array_map(static fn (array $b): array => [
                'kunci' => $b['kunci'], 'jenis' => $b['jenis'], 'label' => $b['label'], 'tanggal' => $b['tanggal'], 'ukuran' => $b['ukuran'],
                'diubah' => date('Y-m-d H:i:s', $b['diubah']), 'hitung' => $lib->hitung($b),
            ], $berkas)),
            'terpilih' => $kunci !== '' ? $kunci : null,
            'saringan' => ['tingkat' => $tingkat, 'q' => $q],
            'isi'      => $isi === null ? null : [
                'jumlah'    => count($entri),
                'terpotong' => $isi['terpotong'],
                'kelompok'  => LogGalat::kelompok($entri),
            ],
            'tingkat' => array_map(static fn (string $k, array $v): array => ['kode' => $k, 'label' => $v[0]], array_keys(LogGalat::TINGKAT), LogGalat::TINGKAT),
            'simpan_hari' => LogGalat::SIMPAN_HARI,
        ], 'Log error hosting.');
    }

    public function bersihkanLog(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $n = (new LogGalat())->bersihkan();
        (new AuditModel())->record('delete', 'log_error', null, 'Bersihkan log error lebih dari ' . LogGalat::SIMPAN_HARI . ' hari: ' . $n . ' berkas (via mobile)');

        return $this->ok(['terhapus' => $n], $n > 0 ? $n . ' berkas log lama dihapus.' : 'Tidak ada log yang lebih tua dari ' . LogGalat::SIMPAN_HARI . ' hari.');
    }

    /** GET sistem/backup — daftar backup (unduh & restore sengaja hanya di web: berkas besar, berisi data pribadi). */
    public function backup(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }

        return $this->ok($this->dataBackup(), 'Daftar backup.');
    }

    /** POST sistem/backup {isi: db|berkas} — buat backup manual. */
    public function buatBackup(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $isi = (string) ($this->body()['isi'] ?? 'db');
        if (! in_array($isi, ['db', 'berkas'], true)) {
            return $this->invalid(['isi' => 'Pilih db (database) atau berkas (berkas unggahan).']);
        }
        $oleh = (string) (($this->admin()['full_name'] ?? '') ?: ($this->admin()['username'] ?? 'Admin'));
        try {
            $b = $isi === 'db' ? BackupSistem::buatDb('manual', $oleh) : BackupSistem::buatBerkas('manual', $oleh);
        } catch (\Throwable $e) {
            log_message('error', '[Backup] API gagal: ' . $e->getMessage());

            return $this->failure('Backup gagal dibuat: ' . $e->getMessage(), 500);
        }
        (new AuditModel())->record('create', 'backup', null, 'Backup ' . ($isi === 'db' ? 'database' : 'berkas unggahan') . ' dibuat: ' . $b['kunci'] . ' (via mobile)');

        return $this->ok($this->dataBackup() + ['dibuat' => $this->bentukBackup($b)], 'Backup ' . ($isi === 'db' ? 'database' : 'berkas unggahan') . ' berhasil dibuat.');
    }

    /** POST sistem/backup/hapus {kunci} */
    public function hapusBackup(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $kunci = (string) ($this->body()['kunci'] ?? '');
        if (! BackupSistem::hapus($kunci)) {
            return $this->failure('Backup tidak ditemukan.', 404);
        }
        (new AuditModel())->record('delete', 'backup', null, 'Backup dihapus: ' . $kunci . ' (via mobile)');

        return $this->ok($this->dataBackup(), 'Backup dihapus.');
    }

    /** GET sistem/sesi — sesi web aktif + perangkat Android; "ini" = token yang sedang dipakai. */
    public function sesi(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }

        return $this->ok($this->dataSesi(), 'Sesi aktif.');
    }

    /** POST sistem/sesi/keluarkan {jenis: web|android, id} */
    public function keluarkanSesi(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $in    = $this->body();
        $jenis = (string) ($in['jenis'] ?? '');
        $id    = (int) ($in['id'] ?? 0);
        if (! in_array($jenis, ['web', 'android'], true) || $id <= 0) {
            return $this->invalid(['jenis' => 'jenis = web atau android, id = angka.']);
        }
        $baris = array_values(array_filter(SesiAktif::daftar(null, ApiAuth::tokenId())[$jenis], static fn (array $r): bool => $r['id'] === $id))[0] ?? null;
        if ($baris === null) {
            return $this->failure('Sesi tidak ditemukan atau sudah berakhir.', 404);
        }
        if ($baris['ini']) {
            return $this->failure('Ini sesi Anda sendiri — pakai menu Keluar.', 422);
        }
        SesiAktif::keluarkan($jenis, $id, $this->oleh());
        (new AuditModel())->record('update', 'sesi', $id, 'Sesi ' . ($jenis === 'web' ? 'web' : 'Android') . ' ' . $baris['nama'] . ' (' . $baris['perangkat'] . ') dikeluarkan oleh ' . $this->oleh() . ' (via mobile)');

        return $this->ok($this->dataSesi(), $baris['nama'] . ' dikeluarkan.');
    }

    /** POST sistem/sesi/keluarkan-semua — semua sesi web & Android selain akun sendiri. */
    public function keluarkanSemuaSesi(): ResponseInterface
    {
        if (($e = $this->sebagaiAdmin()) !== null) {
            return $e;
        }
        $n = SesiAktif::keluarkanSemua($this->oleh(), (int) ($this->admin()['id'] ?? 0));
        (new AuditModel())->record('update', 'sesi', null, 'Semua sesi dikeluarkan (kecuali akun sendiri) oleh ' . $this->oleh() . ': ' . $n['web'] . ' web, ' . $n['android'] . ' Android (via mobile)');

        return $this->ok($this->dataSesi() + ['dikeluarkan' => $n], 'Dikeluarkan: ' . $n['web'] . ' sesi web dan ' . $n['android'] . ' sesi Android.');
    }

    private function dataSesi(): array
    {
        return SesiAktif::daftar(null, ApiAuth::tokenId()) + ['batas_aktif_menit' => intdiv(SesiAktif::BATAS_AKTIF, 60)];
    }

    private function oleh(): string
    {
        return (string) (($this->admin()['full_name'] ?? '') ?: ($this->admin()['username'] ?? 'Admin'));
    }

    private function dataBackup(): array
    {
        $daftar   = BackupSistem::daftar();
        $otomatis = array_values(array_filter($daftar, static fn (array $b): bool => $b['jenis'] === 'otomatis'));

        return [
            'backup'            => array_map($this->bentukBackup(...), $daftar),
            'total_ukuran'      => array_sum(array_column($daftar, 'ukuran')),
            'otomatis_terakhir' => $otomatis[0]['waktu'] ?? null,
            'disimpan'          => array_map(static fn (array $j): int => $j[1], BackupSistem::JENIS),
            'maintenance_aktif' => ModeMaintenance::aktif(),
        ];
    }

    private function bentukBackup(array $b): array
    {
        return ['kunci' => $b['kunci'], 'isi' => $b['isi'], 'jenis' => $b['jenis'], 'label_jenis' => $b['label_jenis'], 'waktu' => $b['waktu'],
            'ukuran' => $b['ukuran'], 'tabel' => $b['tabel'], 'oleh' => $b['oleh']];
    }

    private function sebagaiAdmin(): ?ResponseInterface
    {
        return (string) ($this->admin()['role'] ?? '') === 'admin' ? null : $this->failure('Menu Sistem hanya untuk Admin.', 403);
    }

    private function dataMaintenance(): array
    {
        $s = ModeMaintenance::status();

        return ModeMaintenance::untukApi() + [
            'pesan_tersimpan' => $s['pesan'],
            'selesai_isian'   => $s['aktif'] ? $s['selesai'] : null,
            'oleh'            => $s['aktif'] ? $s['oleh'] : null,
            'dimatikan'       => $s['dimatikan'],
            'dimatikan_oleh'  => $s['dimatikan_oleh'],
            'pesan_bawaan'    => ModeMaintenance::PESAN_BAWAAN,
            'maks_pesan'      => ModeMaintenance::MAKS_PESAN,
        ];
    }
}
