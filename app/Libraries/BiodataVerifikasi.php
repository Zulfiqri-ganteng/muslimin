<?php

namespace App\Libraries;

use App\Models\BiodataIsianModel;
use App\Models\SettingModel;
use App\Models\SiswaModel;

/**
 * Keputusan admin atas isian biodata: setujui (satu/banyak), kembalikan,
 * hapus, pembanding lama-vs-baru, peringatan, dan pengaturan buka/tutup.
 * Dipakai halaman admin web DAN API Android supaya aturannya satu.
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

    /**
     * Setujui banyak isian; tiap isian transaksinya sendiri, jadi satu yang
     * gagal (mis. NISN bentrok) tidak membatalkan yang lain.
     *
     * @param list<int> $ids
     *
     * @return array{ok: int, gagal: list<string>} gagal = "Nama — alasan"
     */
    public function setujuiBanyak(array $ids, ?int $adminId): array
    {
        $ok    = 0;
        $gagal = [];
        foreach ($ids as $id) {
            $h = $this->setujui((int) $id, $adminId);
            if ($h['ok']) {
                $ok++;

                continue;
            }
            $siswa   = db_connect()->table('biodata_isian b')->select('s.nama')
                ->join('siswa s', 's.id = b.siswa_id')->where('b.id', (int) $id)->get()->getRowArray();
            $gagal[] = ($siswa['nama'] ?? '#' . $id) . ' — ' . $h['pesan'];
        }

        return ['ok' => $ok, 'gagal' => $gagal];
    }

    // =================================================================
    // Pembanding & peringatan (halaman Periksa web + API Android)
    // =================================================================

    /**
     * Bandingkan data lama dengan isian siswa, per bagian form.
     * Data lama = Master Siswa sekarang; untuk isian yang SUDAH disetujui
     * Master Siswa sudah sama dengan isian, jadi dipakai potret sebelumnya.
     *
     * @return array{judul_lama: string, bagian: array<string, list<array{kunci:string, label:string, lama:string, baru:string, jenis:string, tetap:bool}>>, hitung: array{baru:int, ubah:int}}
     */
    public static function bandingkan(array $row, ?array $siswa): array
    {
        $sudah = $row['status'] === 'disetujui';
        $lama  = $sudah
            ? (json_decode((string) ($row['data_sebelum'] ?? ''), true) ?: [])
            : array_intersect_key($siswa ?? [], array_flip(BiodataIsianModel::KOLOM));
        $data = BiodataIsianModel::decode($row);

        $bagian = [];
        $hitung = ['baru' => 0, 'ubah' => 0];
        foreach (BiodataForm::BAGIAN as $judul => $kolom) {
            foreach ($kolom as $k) {
                $vLama = self::tampil($k, $lama[$k] ?? null);
                $vBaru = self::tampil($k, $data[$k] ?? null);
                $tetap = $vBaru === '' && in_array($k, self::KOSONG_JANGAN_TIMPA, true);
                $jenis = match (true) {
                    $vLama === $vBaru, $tetap => 'sama',
                    $vLama === ''             => 'baru',
                    default                   => 'ubah',
                };
                if ($jenis !== 'sama') {
                    $hitung[$jenis]++;
                }
                $bagian[$judul][] = [
                    'kunci' => $k,
                    'label' => BiodataForm::LABEL[$k],
                    'lama'  => $vLama,
                    'baru'  => $vBaru,
                    'jenis' => $jenis,
                    // Kosong di isian tapi ada di data lama → data lama dipertahankan.
                    'tetap' => $tetap && $vLama !== '',
                ];
            }
        }

        return [
            'judul_lama' => $sudah ? 'Sebelum disetujui' : 'Data sekarang (Master Siswa)',
            'bagian'     => $bagian,
            'hitung'     => $hitung,
        ];
    }

    /** Nilai siap tampil (tanggal Indonesia, JK lengkap, kosong = ''). */
    public static function tampil(string $k, mixed $v): string
    {
        $v = trim((string) ($v ?? ''));
        if ($v === '') {
            return '';
        }
        if ($k === 'jenis_kelamin') {
            return ['L' => 'Laki-laki', 'P' => 'Perempuan'][$v] ?? $v;
        }
        if (in_array($k, ['tanggal_lahir', 'diterima_tanggal'], true) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

            return (int) $m[3] . ' ' . $bulan[(int) $m[2]] . ' ' . $m[1];
        }

        return $v;
    }

    /**
     * Hal yang perlu diperhatikan sebelum menyetujui (kosong untuk isian
     * yang sudah disetujui atau siswanya sudah tidak ada).
     *
     * @return list<string>
     */
    public static function peringatan(array $row, ?array $siswa): array
    {
        if ($siswa === null || $row['status'] === 'disetujui') {
            return [];
        }
        $data = BiodataIsianModel::decode($row);
        $out  = [];
        $db   = db_connect();
        foreach (['nisn' => 'NISN', 'nis' => 'NIS'] as $kolom => $label) {
            if (empty($data[$kolom])) {
                continue;
            }
            $lain = $db->table('siswa')->select('nama')->where($kolom, $data[$kolom])
                ->where('id !=', (int) $siswa['id'])->where('deleted_at', null)->get()->getRowArray();
            if ($lain !== null) {
                $out[] = $label . ' ' . $data[$kolom] . ' sudah tercatat atas nama ' . $lain['nama']
                    . ' di Master Siswa — persetujuan akan DITOLAK sampai salah satunya dibetulkan.';
            }
        }
        if (mb_strtolower(trim((string) ($data['nama'] ?? ''))) !== mb_strtolower(trim((string) $siswa['nama']))) {
            $out[] = 'Nama di isian berbeda dengan nama di Master Siswa ("' . $siswa['nama'] . '"). Pastikan siswa tidak salah memilih nama temannya.';
        }
        if (($data['jenis_kelamin'] ?? '') !== '' && ($siswa['jenis_kelamin'] ?? '') !== '' && $data['jenis_kelamin'] !== $siswa['jenis_kelamin']) {
            $out[] = 'Jenis kelamin di isian berbeda dengan data sekolah — kemungkinan salah pilih nama.';
        }
        if ((int) $row['kirim_ke'] > 1) {
            $out[] = 'Ini kiriman ke-' . (int) $row['kirim_ke'] . ' (perbaikan dari siswa).';
        }

        return $out;
    }

    // =================================================================
    // Pengaturan buka/tutup
    // =================================================================

    /**
     * Simpan saklar form + batas waktu. Batas boleh kosong; format yang
     * diterima: "Y-m-d H:i", "Y-m-d H:i:s", atau "Y-m-dTH:i" (input HTML).
     *
     * @return array{ok: bool, pesan: string, buka?: int, tutup?: string|null, lewat?: bool}
     */
    public static function simpanPengaturan(bool $buka, string $batas): array
    {
        $batas = trim($batas);
        $tutup = null;
        if ($batas !== '') {
            foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'] as $format) {
                $dt = \DateTimeImmutable::createFromFormat('!' . $format, $batas);
                if ($dt !== false && $dt->format($format) === $batas) {
                    $tutup = $dt->format('Y-m-d H:i:00');
                    break;
                }
            }
            if ($tutup === null) {
                return ['ok' => false, 'pesan' => 'Format batas waktu tidak dikenali (contoh: 2026-10-31 23:59).'];
            }
        }

        (new SettingModel())->store(['biodata_open' => $buka ? 1 : 0, 'biodata_tutup' => $tutup]);
        $lewat = $buka && $tutup !== null && strtotime($tutup) <= time();

        return [
            'ok'    => true,
            'pesan' => $lewat
                ? 'Tersimpan, tetapi batas waktunya sudah lewat sehingga form tetap TERTUTUP bagi siswa. Kosongkan atau mundurkan batas waktunya.'
                : ($buka ? 'Form isian biodata DIBUKA.' : 'Form isian biodata DITUTUP.'),
            'buka'  => $buka ? 1 : 0,
            'tutup' => $tutup,
            'lewat' => $lewat,
        ];
    }
}
