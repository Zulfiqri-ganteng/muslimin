<?php

namespace App\Libraries;

use App\Models\BiodataIsianModel;
use App\Models\SiswaModel;

/**
 * Keputusan admin atas isian biodata: setujui, kembalikan, hapus.
 * Dipakai halaman admin web (dan kelak API Android) supaya aturannya satu.
 *
 * Menyetujui = menulis isian ke Master Siswa dalam SATU transaksi, sambil
 * menyimpan potret data lama (data_sebelum) sebagai jejak audit.
 */
final class BiodataVerifikasi
{
    /**
     * Kolom opsional yang TIDAK dikosongkan bila siswa membiarkannya kosong —
     * nilai yang sudah dicatat sekolah (mis. NIS dari impor) tetap dipakai.
     * Kolom wali sengaja tidak di sini: "tidak punya wali" memang berarti kosong.
     */
    public const KOSONG_JANGAN_TIMPA = ['nis', 'no_hp', 'diterima_tanggal'];

    private BiodataIsianModel $isian;
    private SiswaModel $siswa;

    public function __construct()
    {
        $this->isian = new BiodataIsianModel();
        $this->siswa = new SiswaModel();
    }

    /**
     * Kolom yang akan ditulis ke Master Siswa bila isian ini disetujui.
     *
     * @return array<string, mixed>
     */
    public static function perubahan(array $data): array
    {
        $ubah = [];
        foreach (BiodataIsianModel::KOLOM as $k) {
            $v = $data[$k] ?? null;
            if (($v === null || $v === '') && in_array($k, self::KOSONG_JANGAN_TIMPA, true)) {
                continue;
            }
            $ubah[$k] = ($v === '') ? null : $v;
        }

        return $ubah;
    }

    /** @return array{ok: bool, pesan: string, nama?: string} */
    public function setujui(int $isianId, ?int $adminId): array
    {
        $row = $this->isian->find($isianId);
        if ($row === null) {
            return ['ok' => false, 'pesan' => 'Isian tidak ditemukan.'];
        }
        if ($row['status'] !== 'menunggu') {
            return ['ok' => false, 'pesan' => 'Hanya isian berstatus "menunggu" yang bisa disetujui.'];
        }

        $siswa = $this->siswa->find((int) $row['siswa_id']); // otomatis tanpa yang terhapus
        if ($siswa === null) {
            return ['ok' => false, 'pesan' => 'Siswa ini sudah dihapus dari Master Siswa.'];
        }
        $data = BiodataIsianModel::decode($row);
        if (($data['nama'] ?? '') === '') {
            return ['ok' => false, 'pesan' => 'Isi isian tidak terbaca (rusak).'];
        }

        $ubah    = self::perubahan($data);
        $sebelum = array_intersect_key($siswa, array_flip(BiodataIsianModel::KOLOM));
        $waktu   = date('Y-m-d H:i:s');
        $db      = db_connect();

        $db->transBegin();
        // 'id' ikut dikirim agar placeholder {id} pada aturan is_unique NIS/NISN terisi.
        $okSiswa = $this->siswa->update((int) $siswa['id'], $ubah + ['id' => $siswa['id'], 'biodata_at' => $waktu]);
        if (! $okSiswa) {
            $db->transRollback();
            $galat = array_values($this->siswa->errors());

            return ['ok' => false, 'pesan' => $galat !== [] ? implode(' ', $galat) : 'Master Siswa gagal diperbarui.'];
        }

        $okIsian = $this->isian->update($isianId, [
            'status'            => 'disetujui',
            'data_sebelum'      => json_encode($sebelum, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'diverifikasi_at'   => $waktu,
            'diverifikasi_oleh' => $adminId,
        ]);
        if (! $okIsian || $db->transStatus() === false) {
            $db->transRollback();

            return ['ok' => false, 'pesan' => 'Gagal menyimpan ke database.'];
        }
        $db->transCommit();

        return ['ok' => true, 'pesan' => 'Biodata disetujui dan masuk ke Master Siswa.', 'nama' => (string) $ubah['nama']];
    }

    /** Kembalikan ke siswa untuk diperbaiki (dari menunggu ATAU yang sudah disetujui). */
    public function kembalikan(int $isianId, string $catatan): array
    {
        $catatan = trim(preg_replace('/\s+/u', ' ', $catatan) ?? '');
        if ($catatan === '') {
            return ['ok' => false, 'pesan' => 'Tulis catatan: bagian mana yang harus diperbaiki siswa.'];
        }
        $row = $this->isian->find($isianId);
        if ($row === null) {
            return ['ok' => false, 'pesan' => 'Isian tidak ditemukan.'];
        }
        if ($row['status'] === 'perbaikan') {
            return ['ok' => false, 'pesan' => 'Isian ini sudah menunggu perbaikan dari siswa.'];
        }

        $this->isian->update($isianId, [
            'status'        => 'perbaikan',
            'catatan_admin' => mb_substr($catatan, 0, 255),
        ]);

        return ['ok' => true, 'pesan' => 'Isian dikembalikan. Siswa bisa membukanya lagi di form dengan NISN / tanggal lahir.'];
    }

    /** Hapus isian — siswa bisa mengisi dari nol. Data yang sudah masuk Master Siswa TIDAK ikut terhapus. */
    public function hapus(int $isianId): array
    {
        if ($this->isian->find($isianId) === null) {
            return ['ok' => false, 'pesan' => 'Isian tidak ditemukan.'];
        }
        $this->isian->delete($isianId);

        return ['ok' => true, 'pesan' => 'Isian dihapus. Siswa bisa mengisi ulang dari awal.'];
    }
}
