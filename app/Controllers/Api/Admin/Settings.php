<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AuditModel;
use App\Models\SettingModel;

/**
 * Pengaturan sekolah (API). Cermin App\Controllers\Admin\Settings.
 *
 *   GET  /api/v1/admin/settings   → seluruh pengaturan
 *   POST /api/v1/admin/settings   → simpan (+ logo multipart opsional)
 */
class Settings extends BaseApiController
{
    protected SettingModel $model;

    public function __construct()
    {
        $this->model = new SettingModel();
    }

    public function show()
    {
        return $this->ok(['setting' => $this->transform($this->model->get())]);
    }

    public function save()
    {
        $current = $this->model->get();
        $in      = $this->body();

        $data = [
            'school_name'     => trim((string) ($in['school_name'] ?? '')),
            'school_level'    => trim((string) ($in['school_level'] ?? '')),
            'headmaster_name' => trim((string) ($in['headmaster_name'] ?? '')),
            'headmaster_nip'  => trim((string) ($in['headmaster_nip'] ?? '')),
            'city'            => trim((string) ($in['city'] ?? '')),
            'academic_year'   => trim((string) ($in['academic_year'] ?? '')),
            'address'         => trim((string) ($in['address'] ?? '')),
            'phone'           => trim((string) ($in['phone'] ?? '')),
            'email'           => trim((string) ($in['email'] ?? '')),
            'website'         => trim((string) ($in['website'] ?? '')),
            'form_intro'      => trim((string) ($in['form_intro'] ?? '')),
            'form_open'       => ! empty($in['form_open']) ? 1 : 0,
            'jadwal_publik'   => ! empty($in['jadwal_publik']) ? 1 : 0,
            'absensi_publik'  => ! empty($in['absensi_publik']) ? 1 : 0,
        ];

        // Logo opsional (multipart/form-data, field 'logo')
        $logo = $this->request->getFile('logo');
        if ($logo && $logo->isValid() && ! $logo->hasMoved()) {
            // Deteksi tipe dari ISI berkas (getMimeType), bukan label klien.
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $mime    = $logo->getMimeType();
            if (! isset($allowed[$mime])) {
                return $this->failure('Logo harus berupa gambar (PNG/JPG/WEBP).', 422);
            }
            $path = FCPATH . 'uploads';
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
            $newName = 'logo_' . time() . '.' . $allowed[$mime];
            $logo->move($path, $newName);
            if (! empty($current['logo']) && is_file($path . DIRECTORY_SEPARATOR . $current['logo'])) {
                @unlink($path . DIRECTORY_SEPARATOR . $current['logo']);
            }
            $data['logo'] = $newName;
        }

        $this->model->store($data);
        (new AuditModel())->record('update', 'settings', 1, 'Ubah pengaturan sekolah (via mobile)');

        return $this->ok(['setting' => $this->transform($this->model->get())], 'Pengaturan sekolah berhasil disimpan.');
    }

    private function transform(array $s): array
    {
        return [
            'school_name'     => $s['school_name'] ?? '',
            'school_level'    => $s['school_level'] ?? null,
            'headmaster_name' => $s['headmaster_name'] ?? null,
            'headmaster_nip'  => $s['headmaster_nip'] ?? null,
            'city'            => $s['city'] ?? null,
            'academic_year'   => $s['academic_year'] ?? null,
            'address'         => $s['address'] ?? null,
            'phone'           => $s['phone'] ?? null,
            'email'           => $s['email'] ?? null,
            'website'         => $s['website'] ?? null,
            'form_intro'      => $s['form_intro'] ?? null,
            'form_open'       => (int) ($s['form_open'] ?? 1) === 1,
            'jadwal_publik'   => (int) ($s['jadwal_publik'] ?? 1) === 1,
            'absensi_publik'  => (int) ($s['absensi_publik'] ?? 1) === 1,
            'logo'            => $this->uploadUrl($s['logo'] ?? null),
        ];
    }
}
