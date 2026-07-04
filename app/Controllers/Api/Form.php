<?php

namespace App\Controllers\Api;

use App\Models\SettingModel;
use App\Models\SubmissionModel;

/**
 * Form kesediaan guru (publik) versi API. Cermin App\Controllers\Form.
 *
 *   GET  /api/v1/form/meta            → status form + intro + identitas sekolah
 *   POST /api/v1/form/submit          → kirim kesediaan baru
 *   GET  /api/v1/form/revisi/{token}  → ambil data lama untuk direvisi
 *   POST /api/v1/form/revisi/{token}  → simpan revisi
 */
class Form extends BaseApiController
{
    /** GET meta form. */
    public function meta()
    {
        $s = (new SettingModel())->get();
        return $this->ok([
            'form_open'  => (int) ($s['form_open'] ?? 1) === 1,
            'form_intro' => $s['form_intro'] ?? null,
            'school'     => [
                'name'          => $s['school_name'] ?? '',
                'academic_year' => $s['academic_year'] ?? null,
                'logo'          => $this->uploadUrl($s['logo'] ?? null),
            ],
        ]);
    }

    /** POST kirim kesediaan baru. */
    public function submit()
    {
        $s = (new SettingModel())->get();
        if (empty($s['form_open'])) {
            return $this->forbidden('Pengisian formulir sedang ditutup.');
        }

        $in     = $this->body();
        $errors = $this->validateForm($in);
        if ($errors) {
            return $this->invalid($errors);
        }

        $data               = $this->buildData($in);
        $data['status']     = 'baru';
        $data['ip_address'] = $this->request->getIPAddress();

        (new SubmissionModel())->insert($data);

        return $this->created(['nama_lengkap' => $data['nama_lengkap']], 'Terima kasih, kesediaan Anda telah terkirim.');
    }

    /** GET data lama untuk revisi (hanya berlaku bila status 'ditolak'). */
    public function revisi(string $token)
    {
        $row = (new SubmissionModel())->where('edit_token', $token)->first();
        if (! $row || $row['status'] !== 'ditolak') {
            return $this->failure('Tautan revisi tidak valid atau sudah tidak berlaku.', 410);
        }

        $data = SubmissionModel::decode($row);
        return $this->ok([
            'admin_note' => $row['catatan_admin'] ?? null,
            'data'       => $this->publicSubmission($data),
        ]);
    }

    /** POST simpan revisi. */
    public function updateRevisi(string $token)
    {
        $model = new SubmissionModel();
        $row   = $model->where('edit_token', $token)->first();
        if (! $row || $row['status'] !== 'ditolak') {
            return $this->failure('Tautan revisi tidak valid atau sudah tidak berlaku.', 410);
        }

        $in     = $this->body();
        $errors = $this->validateForm($in);
        if ($errors) {
            return $this->invalid($errors);
        }

        $data               = $this->buildData($in);
        $data['status']     = 'baru'; // kembali ke antrian tinjau
        $data['edit_token'] = null;   // tautan revisi sekali pakai

        $model->update($row['id'], $data);

        return $this->ok(['nama_lengkap' => $data['nama_lengkap']], 'Revisi berhasil dikirim.');
    }

    // ===================== HELPER =====================

    /** Validasi mengikuti aturan web (submit & revisi). Kembalikan map error atau []. */
    private function validateForm(array $in): array
    {
        $errors   = [];
        $nama     = trim((string) ($in['nama_lengkap'] ?? ''));
        $bersedia = (string) ($in['bersedia'] ?? '');

        if ($nama === '') {
            $errors['nama_lengkap'] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($nama) > 150) {
            $errors['nama_lengkap'] = 'Nama lengkap maksimal 150 karakter.';
        }
        if (! in_array($bersedia, ['ya', 'tidak'], true)) {
            $errors['bersedia'] = 'Silakan pilih kesediaan Anda terlebih dahulu.';
        }

        if ($bersedia === 'ya') {
            if (trim((string) ($in['nomor_hp'] ?? '')) === '') {
                $errors['nomor_hp'] = 'Nomor HP wajib diisi.';
            }
            if (! in_array((string) ($in['status_kepegawaian'] ?? ''), ['PNS', 'PPPK', 'GTY', 'GTT'], true)) {
                $errors['status_kepegawaian'] = 'Status kepegawaian tidak valid.';
            }
            if (empty($in['komitmen_setuju'])) {
                $errors['komitmen_setuju'] = 'Anda harus menyetujui pernyataan kesediaan & komitmen.';
            }
        }

        return $errors;
    }

    /** Susun data DB dari JSON rapi (semantik sama dengan App\Controllers\Form::buildData). */
    private function buildData(array $in): array
    {
        // mapel_diampu: [ {mapel, kelas} ]
        $mapel = [];
        foreach ((array) ($in['mapel_diampu'] ?? []) as $m) {
            $nm = trim((string) ($m['mapel'] ?? ''));
            if ($nm === '') {
                continue;
            }
            $mapel[] = ['mapel' => $nm, 'kelas' => trim((string) ($m['kelas'] ?? ''))];
        }

        // ketersediaan_hari: { "Senin": ["Pagi","Siang"], ... }
        $hari = [];
        foreach ((array) ($in['ketersediaan_hari'] ?? []) as $h => $sesi) {
            $pilih = array_values(array_filter((array) $sesi, static fn ($x) => in_array($x, ['Pagi', 'Siang'], true)));
            if ($pilih) {
                $hari[$h] = $pilih;
            }
        }

        $bersedia      = ($in['bersedia'] ?? '') === 'ya';
        $tugasBersedia = ! empty($in['tugas_bersedia']);

        return [
            'nama_lengkap'        => trim((string) ($in['nama_lengkap'] ?? '')),
            'nip_nuptk'           => null,
            'tempat_lahir'        => null,
            'tanggal_lahir'       => null,
            'pendidikan_terakhir' => trim((string) ($in['pendidikan_terakhir'] ?? '')),
            'guru_mapel'          => trim((string) ($in['guru_mapel'] ?? '')),
            'status_kepegawaian'  => ($in['status_kepegawaian'] ?? '') ?: null,
            'nomor_hp'            => trim((string) ($in['nomor_hp'] ?? '')),
            'mapel_diampu'        => json_encode($mapel, JSON_UNESCAPED_UNICODE),
            'total_jam'           => 0,
            'tugas_tambahan'      => json_encode($tugasBersedia ? ['Bersedia menerima tugas tambahan'] : [], JSON_UNESCAPED_UNICODE),
            'tugas_lainnya'       => null,
            'kesediaan_jam'       => json_encode(array_values((array) ($in['kesediaan_jam'] ?? [])), JSON_UNESCAPED_UNICODE),
            'preferensi'          => json_encode([], JSON_UNESCAPED_UNICODE),
            'ketersediaan_hari'   => json_encode($hari, JSON_UNESCAPED_UNICODE),
            'keterangan_tambahan' => trim((string) ($in['keterangan_tambahan'] ?? '')),
            'bersedia_mengajar'   => $bersedia ? 1 : 0,
            'komitmen_setuju'     => ($bersedia && ! empty($in['komitmen_setuju'])) ? 1 : 0,
        ];
    }

    /** Bentuk data submission (JSON fields sudah ter-decode) untuk prefill form revisi. */
    private function publicSubmission(array $r): array
    {
        return [
            'nama_lengkap'        => $r['nama_lengkap'] ?? '',
            'bersedia'            => (int) ($r['bersedia_mengajar'] ?? 0) === 1 ? 'ya' : 'tidak',
            'pendidikan_terakhir' => $r['pendidikan_terakhir'] ?? '',
            'guru_mapel'          => $r['guru_mapel'] ?? '',
            'status_kepegawaian'  => $r['status_kepegawaian'] ?? '',
            'nomor_hp'            => $r['nomor_hp'] ?? '',
            'mapel_diampu'        => $r['mapel_diampu'] ?? [],
            'tugas_bersedia'      => ! empty($r['tugas_tambahan']),
            'kesediaan_jam'       => $r['kesediaan_jam'] ?? [],
            'ketersediaan_hari'   => $r['ketersediaan_hari'] ?? [],
            'keterangan_tambahan' => $r['keterangan_tambahan'] ?? '',
        ];
    }
}
