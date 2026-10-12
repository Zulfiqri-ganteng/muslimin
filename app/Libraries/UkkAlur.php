<?php

namespace App\Libraries;

use App\Models\JadwalUkkModel;
use App\Models\JadwalUkkPengujiModel;
use App\Models\NilaiUkkModel;
use App\Models\PaketSoalUkkModel;
use App\Models\PesertaUkkModel;
use App\Models\SertifikatUkkModel;
use DateTime;

/**
 * Aturan alur kerja UKK yang dipakai BERSAMA web (App\Controllers\Admin\PesertaUkk/PenilaianUkk/SertifikatUkk) dan
 * API (Api\Admin\*Ukk), supaya hasil dari aplikasi Android selalu sama persis dengan dari situs web.
 *
 * Tiap metode mengembalikan array. Gagal = ['ok' => false, 'kode' => 404|409|422, 'pesan' => '…',
 * 'errors' => [kolom => pesan]]; berhasil = ['ok' => true, …data]. Jejak audit tetap di controller (teksnya beda).
 */
class UkkAlur
{
    /** Lima komponen skor penilaian (0–100), bobotnya milik paket soal. */
    public const SKOR = ['persiapan_skor', 'proses_skor', 'hasil_skor', 'sikap_skor', 'waktu_skor'];

    /**
     * Daftarkan banyak siswa ke satu paket soal sekaligus (no_peserta otomatis "UKK-{KODE}-001").
     * Pendaftaran lama yang dihapus dipulihkan (bukan bentrok dengan UNIQUE siswa+paket); yang masih aktif dilewati.
     * Jadwal (opsional) harus milik paket itu; id siswa yang tidak ada diabaikan.
     */
    public static function daftarkan(int $paketId, ?int $jadwalId, array $siswaIds): array
    {
        $paket = $paketId > 0 ? (new PaketSoalUkkModel())->find($paketId) : null;
        if (! $paket) {
            return self::gagal('Paket soal tidak ditemukan.', 404, 'paket_soal_id');
        }
        if ($jadwalId !== null) {
            $jadwal = (new JadwalUkkModel())->find($jadwalId);
            if (! $jadwal || (int) $jadwal['paket_soal_id'] !== $paketId) {
                return self::gagal('Jadwal tidak ditemukan pada paket soal ini.', 422, 'jadwal_ukk_id');
            }
        }
        $siswaIds = array_values(array_unique(array_filter(array_map('intval', $siswaIds))));
        if ($siswaIds !== []) {
            $siswaIds = array_map('intval', array_column(
                db_connect()->table('siswa')->select('id')->whereIn('id', $siswaIds)->where('deleted_at', null)->get()->getResultArray(),
                'id'
            ));
        }
        if ($siswaIds === []) {
            return self::gagal('Pilih minimal satu siswa untuk didaftarkan.', 422, 'siswa_ids');
        }

        $model = new PesertaUkkModel();
        $db    = db_connect();
        $db->transException(true);
        $db->transStart();

        $didaftarkan = 0;
        $dilewati    = 0;
        foreach ($siswaIds as $sid) {
            // Cek termasuk yang soft-deleted, agar pendaftaran lama bisa dipulihkan
            // alih-alih bentrok dengan UNIQUE(siswa_id, paket_soal_id).
            $lama = $model->withDeleted()->where(['siswa_id' => $sid, 'paket_soal_id' => $paketId])->first();
            if ($lama && $lama['deleted_at'] === null) {
                $dilewati++;

                continue;
            }

            $data = [
                'siswa_id'        => $sid,
                'paket_soal_id'   => $paketId,
                'jadwal_ukk_id'   => $jadwalId,
                'tahun_ajaran_id' => $paket['tahun_ajaran_id'],
                'no_peserta'      => $model->nomorBerikutnya($paket['kode']),
                'status'          => 'terdaftar',
            ];
            if ($lama) {
                $model->protect(false);
                $model->update($lama['id'], $data + ['deleted_at' => null]);
                $model->protect(true);
            } else {
                $model->insert($data);
            }
            $didaftarkan++;
        }

        $db->transComplete();
        master_data_changed('peserta_ukk');

        return ['ok' => true, 'paket' => $paket, 'didaftarkan' => $didaftarkan, 'dilewati' => $dilewati];
    }

    /**
     * Simpan (insert/update) satu sel nilai peserta × penguji pada satu jadwal, lalu perbarui agregat peserta:
     * nilai_akhir = rata-rata semua penguji, status lulus/tidak_lulus + predikat mengikuti KKM paket soal.
     *
     * $in: peserta_ukk_id, tipe_penguji (internal|eksternal), guru_id | penguji_eksternal_id, 5 skor (0–100, kosong
     * boleh), tanggal_nilai (TTTT-BB-HH, kosong = hari ini), keterangan.
     */
    public static function simpanNilai(int $jadwalId, array $in): array
    {
        $jadwal = (new JadwalUkkModel())->find($jadwalId);
        if (! $jadwal) {
            return self::gagal('Jadwal tidak ditemukan.', 404);
        }

        $pesertaId = (int) ($in['peserta_ukk_id'] ?? 0);
        $tipe      = strtolower(trim((string) ($in['tipe_penguji'] ?? '')));
        $guruId    = $tipe === 'internal' ? ((int) ($in['guru_id'] ?? 0) ?: null) : null;
        $eksId     = $tipe === 'eksternal' ? ((int) ($in['penguji_eksternal_id'] ?? 0) ?: null) : null;

        $pesertaModel = new PesertaUkkModel();
        $peserta      = $pesertaModel->find($pesertaId);
        if (! $peserta || (int) $peserta['jadwal_ukk_id'] !== $jadwalId) {
            return self::gagal('Peserta tidak valid untuk jadwal ini.', 422, 'peserta_ukk_id');
        }
        if (! in_array($tipe, ['internal', 'eksternal'], true)) {
            return self::gagal('Tipe penguji tidak valid.', 422, 'tipe_penguji');
        }

        // Pastikan penguji memang ditugaskan pada jadwal ini (anti tamper).
        $tugas = (new JadwalUkkPengujiModel())->where('jadwal_ukk_id', $jadwalId)->where('tipe', $tipe);
        $tugas = $tipe === 'internal' ? $tugas->where('guru_id', $guruId) : $tugas->where('penguji_eksternal_id', $eksId);
        if ($tugas->countAllResults() === 0) {
            return self::gagal('Penguji tidak ditugaskan pada jadwal ini.', 422, $tipe === 'internal' ? 'guru_id' : 'penguji_eksternal_id');
        }

        $skor   = [];
        $errors = [];
        foreach (self::SKOR as $k) {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v !== '' && (! is_numeric($v) || (float) $v < 0 || (float) $v > 100)) {
                $errors[$k] = 'Skor harus angka 0–100.';
            }
            $skor[$k] = $v === '' ? null : (float) $v;
        }
        $tanggal = trim((string) ($in['tanggal_nilai'] ?? '')) ?: date('Y-m-d');
        if (! self::tanggalSah($tanggal)) {
            $errors['tanggal_nilai'] = 'Format tanggal harus TTTT-BB-HH.';
        }
        $keterangan = trim((string) ($in['keterangan'] ?? '')) ?: null;
        if ($keterangan !== null && mb_strlen($keterangan) > 255) {
            $errors['keterangan'] = 'Keterangan maksimal 255 karakter.';
        }
        if ($errors !== []) {
            return self::gagal(implode(' ', array_unique($errors)), 422, null, $errors);
        }

        $paket      = (new PaketSoalUkkModel())->find($jadwal['paket_soal_id']);
        $nilaiModel = new NilaiUkkModel();
        $data = array_merge($skor, [
            'peserta_ukk_id'       => $pesertaId,
            'tipe_penguji'         => $tipe,
            'guru_id'              => $guruId,
            'penguji_eksternal_id' => $eksId,
            'nilai_akhir'          => $nilaiModel->hitungNilaiAkhir($skor, $paket ?: []),
            'tanggal_nilai'        => $tanggal,
            'keterangan'           => $keterangan,
        ]);

        $lamaQ = $nilaiModel->withDeleted()->where('peserta_ukk_id', $pesertaId)->where('tipe_penguji', $tipe);
        $lamaQ = $tipe === 'internal' ? $lamaQ->where('guru_id', $guruId) : $lamaQ->where('penguji_eksternal_id', $eksId);
        $lama  = $lamaQ->first();

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        if ($lama) {
            $nilaiModel->protect(false);
            $ok = $nilaiModel->update($lama['id'], $data + ['deleted_at' => null]);
            $nilaiModel->protect(true);
            $nilaiId = (int) $lama['id'];
        } else {
            $ok      = $nilaiModel->insert($data) !== false;
            $nilaiId = (int) $nilaiModel->getInsertID();
        }

        if (! $ok) {
            $db->transRollback();

            return self::gagal(implode(' ', $nilaiModel->errors()), 422, null, $nilaiModel->errors());
        }

        // Perbarui agregat nilai_akhir peserta + status kelulusan (vs KKM paket).
        $rata   = $nilaiModel->rataRataUntukPeserta($pesertaId);
        $update = ['nilai_akhir' => $rata];
        if ($rata !== null && $paket) {
            $lulus              = $rata >= (float) $paket['kkm'];
            $update['status']   = $lulus ? 'lulus' : 'tidak_lulus';
            $update['predikat'] = $lulus ? 'Kompeten' : 'Belum Kompeten';
        }
        $pesertaModel->update($pesertaId, $update);
        $db->transComplete();

        master_data_changed('peserta_ukk');

        return [
            'ok'         => true,
            'baru'       => ! $lama,
            'nilai_id'   => $nilaiId,
            'peserta_id' => $pesertaId,
            'tipe'       => $tipe,
        ];
    }

    /**
     * Terbitkan sertifikat untuk peserta berstatus 'lulus' (1 peserta = 1 sertifikat, nomor otomatis
     * "SERT-UKK-{tahun}-001"). Sertifikat yang dulu DIHAPUS dipulihkan dengan nomor lamanya — tanpa ini peserta itu
     * tak bisa diberi sertifikat lagi karena UNIQUE(peserta_ukk_id) ikut menghitung baris yang terhapus.
     */
    public static function terbitkanSertifikat(int $pesertaId, string $tanggal, ?string $keterangan): array
    {
        $peserta = $pesertaId > 0 ? (new PesertaUkkModel())->find($pesertaId) : null;
        if (! $peserta) {
            return self::gagal($pesertaId > 0 ? 'Peserta tidak ditemukan.' : 'Peserta wajib dipilih.', 422, 'peserta_ukk_id');
        }
        if ($peserta['status'] !== 'lulus') {
            return self::gagal('Sertifikat hanya bisa diterbitkan untuk peserta berstatus lulus.', 422, 'peserta_ukk_id');
        }
        $tanggal = $tanggal !== '' ? $tanggal : date('Y-m-d');
        if (! self::tanggalSah($tanggal)) {
            return self::gagal('Format tanggal harus TTTT-BB-HH.', 422, 'tanggal_terbit');
        }

        $model = new SertifikatUkkModel();
        $lama  = $model->withDeleted()->where('peserta_ukk_id', $pesertaId)->first();
        if ($lama && $lama['deleted_at'] === null) {
            return self::gagal('Peserta ini sudah punya sertifikat (' . $lama['nomor_sertifikat'] . ').', 409, 'peserta_ukk_id');
        }

        $data = ['peserta_ukk_id' => $pesertaId, 'tanggal_terbit' => $tanggal, 'keterangan' => $keterangan ?: null];
        if ($lama) {
            $model->protect(false);
            $ok = $model->update($lama['id'], $data + ['id' => $lama['id'], 'deleted_at' => null]);
            $model->protect(true);
            $id    = (int) $lama['id'];
            $nomor = $lama['nomor_sertifikat'];
        } else {
            $data['nomor_sertifikat'] = $model->nomorBerikutnya();
            $ok    = $model->insert($data) !== false;
            $id    = (int) $model->getInsertID();
            $nomor = $data['nomor_sertifikat'];
        }
        if (! $ok) {
            return self::gagal(implode(' ', $model->errors()), 422, null, $model->errors());
        }

        return ['ok' => true, 'id' => $id, 'nomor' => $nomor, 'dipulihkan' => (bool) $lama];
    }

    /** Tanggal berformat TTTT-BB-HH dan benar-benar ada di kalender? */
    public static function tanggalSah(string $v): bool
    {
        $d = DateTime::createFromFormat('!Y-m-d', $v);

        return $d !== false && $d->format('Y-m-d') === $v;
    }

    private static function gagal(string $pesan, int $kode = 422, ?string $kolom = null, array $errors = []): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan, 'errors' => $errors !== [] ? $errors : [($kolom ?? 'umum') => $pesan]];
    }
}
