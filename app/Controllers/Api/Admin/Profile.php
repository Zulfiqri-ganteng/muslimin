<?php

namespace App\Controllers\Api\Admin;

use App\Controllers\Api\BaseApiController;
use App\Models\AdminModel;

/**
 * Profil admin (API). Cermin App\Controllers\Admin\Profile.
 *
 *   GET  /api/v1/admin/profile              → profil saya
 *   POST /api/v1/admin/profile              → ubah nama/username/email/hp (+foto multipart opsional)
 *   POST /api/v1/admin/profile/password     → ganti password
 */
class Profile extends BaseApiController
{
    protected AdminModel $model;

    public function __construct()
    {
        $this->model = new AdminModel();
    }

    public function show()
    {
        return $this->ok(['admin' => $this->publicAdmin($this->model->find($this->adminId()))]);
    }

    public function update()
    {
        $id      = (int) $this->adminId();
        $in      = $this->body();
        $current = $this->model->find($id);

        $data = [
            'full_name' => trim((string) ($in['full_name'] ?? '')),
            'username'  => trim((string) ($in['username'] ?? '')),
            'email'     => trim((string) ($in['email'] ?? '')),
            'phone'     => trim((string) ($in['phone'] ?? '')),
        ];

        $rules = [
            'full_name' => 'required|max_length[150]',
            'username'  => "required|min_length[4]|max_length[100]|is_unique[admins.username,id,{$id}]",
            'email'     => "required|valid_email|is_unique[admins.email,id,{$id}]",
        ];
        $v = service('validation');
        $v->setRules($rules);
        if (! $v->run($data)) {
            return $this->invalid($v->getErrors());
        }

        // Foto opsional (multipart/form-data, field 'photo')
        $photo = $this->request->getFile('photo');
        if ($photo && $photo->isValid() && ! $photo->hasMoved()) {
            if (! in_array($photo->getClientMimeType(), ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'], true)) {
                return $this->failure('Foto harus berupa gambar (PNG/JPG/WEBP).', 422);
            }
            $path = FCPATH . 'uploads';
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
            $newName = 'admin_' . $id . '_' . time() . '.' . $photo->getExtension();
            $photo->move($path, $newName);
            if (! empty($current['photo']) && is_file($path . DIRECTORY_SEPARATOR . $current['photo'])) {
                @unlink($path . DIRECTORY_SEPARATOR . $current['photo']);
            }
            $data['photo'] = $newName;
        }

        $this->model->update($id, $data);

        return $this->ok(['admin' => $this->publicAdmin($this->model->find($id))], 'Profil berhasil diperbarui.');
    }

    public function password()
    {
        $id    = (int) $this->adminId();
        $admin = $this->model->find($id);
        $in    = $this->body();

        $old     = (string) ($in['old_password'] ?? '');
        $new     = (string) ($in['new_password'] ?? '');
        $confirm = (string) ($in['confirm_password'] ?? '');

        if (! password_verify($old, $admin['password'])) {
            return $this->failure('Password lama salah.', 422);
        }
        if (strlen($new) < 6) {
            return $this->invalid(['new_password' => 'Password baru minimal 6 karakter.']);
        }
        if ($new !== $confirm) {
            return $this->invalid(['confirm_password' => 'Konfirmasi password tidak cocok.']);
        }

        $this->model->update($id, ['password' => password_hash($new, PASSWORD_DEFAULT)]);

        return $this->ok(null, 'Password berhasil diganti.');
    }

    private function publicAdmin(array $a): array
    {
        return [
            'id'        => (int) $a['id'],
            'full_name' => $a['full_name'] ?? '',
            'username'  => $a['username'] ?? '',
            'email'     => $a['email'] ?? '',
            'phone'     => $a['phone'] ?? null,
            'photo'     => $this->uploadUrl($a['photo'] ?? null),
            'role'      => $a['role'] ?? 'admin',
        ];
    }
}
