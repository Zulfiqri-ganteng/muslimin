<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminModel;

class Profile extends BaseController
{
    protected AdminModel $model;

    public function __construct()
    {
        $this->model = new AdminModel();
    }

    public function index()
    {
        $admin = $this->model->find(session('admin.id'));
        return view('admin/profile', [
            'title' => 'Profil Saya',
            'admin' => $admin,
        ]);
    }

    /** Update data profil (nama, username, email, hp, foto). */
    public function update()
    {
        $id = session('admin.id');

        $rules = [
            'full_name' => 'required|max_length[150]',
            'username'  => "required|min_length[4]|max_length[100]|is_unique[admins.username,id,{$id}]",
            'email'     => "required|valid_email|is_unique[admins.email,id,{$id}]",
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $current = $this->model->find($id);
        $data = [
            'full_name' => trim((string) $this->request->getPost('full_name')),
            'username'  => trim((string) $this->request->getPost('username')),
            'email'     => trim((string) $this->request->getPost('email')),
            'phone'     => trim((string) $this->request->getPost('phone')),
        ];

        // Upload foto (opsional)
        $photo = $this->request->getFile('photo');
        if ($photo && $photo->isValid() && ! $photo->hasMoved()) {
            // Deteksi tipe dari ISI berkas, bukan label klien yang bisa keliru.
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $mime    = $photo->getMimeType();
            if (! isset($allowed[$mime])) {
                return redirect()->back()->with('error', 'Foto harus berupa gambar (PNG/JPG/WEBP).');
            }
            $path = FCPATH . 'uploads';
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
            $newName = 'admin_' . $id . '_' . time() . '.' . $allowed[$mime];
            $photo->move($path, $newName);
            if (! empty($current['photo']) && is_file($path . DIRECTORY_SEPARATOR . $current['photo'])) {
                @unlink($path . DIRECTORY_SEPARATOR . $current['photo']);
            }
            $data['photo'] = $newName;
        }

        // Validasi sudah dilakukan di atas dengan $id yang benar. Lewati validasi
        // bawaan model (placeholder {id} tak terisi saat update → is_unique keliru
        // menolak sehingga update gagal diam-diam).
        $this->model->skipValidation(true)->update($id, $data);

        // Segarkan session
        $fresh = $this->model->find($id);
        unset($fresh['password']);
        session()->set('admin', $fresh);

        return redirect()->to(site_url('admin/profile'))->with('success', 'Profil berhasil diperbarui.');
    }

    /** Ganti password. */
    public function password()
    {
        $id      = session('admin.id');
        $admin   = $this->model->find($id);

        $old     = (string) $this->request->getPost('old_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if (! password_verify($old, $admin['password'])) {
            return redirect()->back()->with('error', 'Password lama salah.');
        }
        if (strlen($new) < 6) {
            return redirect()->back()->with('error', 'Password baru minimal 6 karakter.');
        }
        if ($new !== $confirm) {
            return redirect()->back()->with('error', 'Konfirmasi password tidak cocok.');
        }

        $this->model->update($id, ['password' => password_hash($new, PASSWORD_DEFAULT)]);

        return redirect()->to(site_url('admin/profile'))->with('success', 'Password berhasil diganti.');
    }
}
