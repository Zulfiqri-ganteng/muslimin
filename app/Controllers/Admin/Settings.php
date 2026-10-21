<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakPeran;
use App\Libraries\Tampilan;
use App\Models\SettingModel;

class Settings extends BaseController
{
    protected SettingModel $model;

    public function __construct()
    {
        $this->model = new SettingModel();
    }

    public function index()
    {
        $setting = $this->model->get();

        return view('admin/settings', [
            'title'     => 'Pengaturan Sekolah',
            'setting'   => $setting,
            'tema'      => Tampilan::tema($setting),
            'temaSiap'  => Tampilan::temaSiap(),
            'bolehTema' => (string) (session('admin')['role'] ?? '') === HakPeran::ADMIN,
        ]);
    }

    /**
     * POST admin/settings/tampilan — mode tampilan panel admin (Terang / Gelap / Warna Sekolah) untuk SEMUA akun. Khusus peran Admin.
     * Isian: pilihan = kunci Tampilan::MODE ("terang", "gelap", "sekolah").
     */
    public function simpanTampilan()
    {
        if ((string) (session('admin')['role'] ?? '') !== HakPeran::ADMIN) {
            return redirect()->to(site_url('admin/settings'))->with('error', 'Tema tampilan hanya bisa diganti akun Admin.');
        }
        if (! Tampilan::temaSiap()) {
            return redirect()->to(site_url('admin/settings') . '#tampilan')->with('error', 'Kolom tema belum ada di database. Jalankan migrasi dulu (php spark migrate).');
        }

        $pilihan = (string) $this->request->getPost('pilihan');
        if (! isset(Tampilan::MODE[$pilihan])) {
            return redirect()->to(site_url('admin/settings') . '#tampilan')->with('error', 'Pilihan tema tidak dikenali.');
        }

        // Sengaja TIDAK dicatat di Audit Log (permintaan 2026-10-21): ganti tema hanya urusan tampilan, bukan perubahan data.
        $this->model->store(['tema_warna' => Tampilan::MODE[$pilihan]['warna'], 'tema_sidebar' => Tampilan::MODE[$pilihan]['sidebar']]);
        $nama = Tampilan::namaTema($pilihan);

        return redirect()->to(site_url('admin/settings') . '#tampilan')->with('success', 'Tema tampilan disimpan: ' . $nama . '. Berlaku untuk semua akun.');
    }

    public function save()
    {
        $current = $this->model->get();

        $data = [
            'school_name'     => trim((string) $this->request->getPost('school_name')),
            'school_level'    => trim((string) $this->request->getPost('school_level')),
            'headmaster_name' => \App\Libraries\IsianBantu::rapikanGelar((string) $this->request->getPost('headmaster_name')),
            'headmaster_nip'  => trim((string) $this->request->getPost('headmaster_nip')),
            // Untuk Daftar Nama Siswa (Format 8355)
            'supervisor_name'      => trim((string) $this->request->getPost('supervisor_name')),
            'supervisor_nip'       => trim((string) $this->request->getPost('supervisor_nip')),
            'school_postal_code'   => trim((string) $this->request->getPost('school_postal_code')),
            'school_accreditation' => trim((string) $this->request->getPost('school_accreditation')),
            'city'            => trim((string) $this->request->getPost('city')),
            'academic_year'   => trim((string) $this->request->getPost('academic_year')),
            'address'         => trim((string) $this->request->getPost('address')),
            'phone'           => trim((string) $this->request->getPost('phone')),
            'email'           => trim((string) $this->request->getPost('email')),
            'website'         => trim((string) $this->request->getPost('website')),
            'form_intro'      => trim((string) $this->request->getPost('form_intro')),
            'form_open'       => $this->request->getPost('form_open') ? 1 : 0,
            'jadwal_publik'   => $this->request->getPost('jadwal_publik') ? 1 : 0,
            'absensi_publik'  => $this->request->getPost('absensi_publik') ? 1 : 0,

            // --- Manajemen Dokumen ---
            // Batas ukuran dijepit 1–200 MB: nilai di luar itu percuma karena
            // server tetap punya batas sendiri (upload_max_filesize).
            'dok_maks_mb'       => min(200, max(1, (int) $this->request->getPost('dok_maks_mb'))),
            'dok_kuota_mb'      => max(100, (int) $this->request->getPost('dok_kuota_mb')),
            'dok_izinkan_video' => $this->request->getPost('dok_izinkan_video') ? 1 : 0,
            'dokumen_publik'    => $this->request->getPost('dokumen_publik') ? 1 : 0,
        ];

        // --- Situs publik (rombak publik P2b): profil singkat, WhatsApp bantuan, tautan unduh aplikasi Android; kosong = tidak tampil.
        // Dicek SEBELUM logo dipindahkan, agar isian salah tidak meninggalkan logo baru yang tak tercatat.
        $wa       = (string) preg_replace('/[^\d+]/', '', (string) $this->request->getPost('wa_bantuan'));
        $aplikasi = trim((string) $this->request->getPost('tautan_aplikasi'));
        if ($aplikasi !== '' && preg_match('#^https?://#i', $aplikasi) !== 1) {
            $aplikasi = 'https://' . $aplikasi;
        }
        if ($wa !== '' && preg_match('/^\+?\d{9,15}$/', $wa) !== 1) {
            return redirect()->back()->withInput()->with('error', 'Nomor WhatsApp bantuan tidak valid. Tulis 9–15 angka, mis. 081234567890.');
        }
        if ($aplikasi !== '' && (mb_strlen($aplikasi) > 255 || filter_var($aplikasi, FILTER_VALIDATE_URL) === false)) {
            return redirect()->back()->withInput()->with('error', 'Tautan aplikasi Android tidak valid. Salin alamat lengkapnya, mis. https://play.google.com/store/apps/details?id=…');
        }
        $data['profil_singkat']  = mb_substr(trim((string) $this->request->getPost('profil_singkat')), 0, 1000);
        $data['wa_bantuan']      = $wa;
        $data['tautan_aplikasi'] = $aplikasi;

        // Upload logo (opsional)
        $logo = $this->request->getFile('logo');
        if ($logo && $logo->isValid() && ! $logo->hasMoved()) {
            // Deteksi tipe dari ISI berkas, bukan label klien yang bisa keliru.
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $mime    = $logo->getMimeType();
            if (! isset($allowed[$mime])) {
                return redirect()->back()->with('error', 'Logo harus berupa gambar (PNG/JPG/WEBP).');
            }
            $path    = FCPATH . 'uploads';
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
            $newName = 'logo_' . time() . '.' . $allowed[$mime];
            $logo->move($path, $newName);

            // hapus logo lama
            if (! empty($current['logo']) && is_file($path . DIRECTORY_SEPARATOR . $current['logo'])) {
                @unlink($path . DIRECTORY_SEPARATOR . $current['logo']);
            }
            $data['logo'] = $newName;
        }

        $this->model->store($data);

        return redirect()->to(site_url('admin/settings'))->with('success', 'Pengaturan sekolah berhasil disimpan.');
    }
}
