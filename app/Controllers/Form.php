<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Models\SubmissionModel;

class Form extends BaseController
{
    protected SettingModel $settings;

    public function __construct()
    {
        $this->settings = new SettingModel();
    }

    /** Landing page / beranda sistem. */
    public function home()
    {
        return view('public/home', [
            'title'   => 'Beranda',
            'setting' => $this->settings->get(),
        ]);
    }

    /** Halaman form publik untuk guru. */
    public function index()
    {
        $setting = $this->settings->get();
        if (empty($setting['form_open'])) {
            return redirect()->to(site_url('tutup'));
        }

        return view('public/form', [
            'title'   => 'Format Kesediaan Guru Mengajar',
            'setting' => $setting,
        ]);
    }

    /** Form sedang ditutup. */
    public function closed()
    {
        return view('public/closed', [
            'title'   => 'Pengisian Ditutup',
            'setting' => $this->settings->get(),
        ]);
    }

    /** Proses pengiriman form (pengisian baru). */
    public function submit()
    {
        $setting = $this->settings->get();
        if (empty($setting['form_open'])) {
            return redirect()->to(site_url('tutup'));
        }

        $bersedia = $this->request->getPost('bersedia');
        $rules = [
            'nama_lengkap' => 'required|max_length[150]',
            'bersedia'     => 'required|in_list[ya,tidak]',
        ];
        if ($bersedia === 'ya') {
            $rules['nomor_hp']           = 'required|max_length[30]';
            $rules['status_kepegawaian'] = 'required|in_list[PNS,PPPK,GTY,GTT]';
            $rules['komitmen_setuju']    = 'required';
        }

        if (! $this->validate($rules, $this->validationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data               = $this->buildData($this->request->getPost());
        $data['status']     = 'baru';
        $data['ip_address'] = $this->request->getIPAddress();

        (new SubmissionModel())->insert($data);

        return redirect()->to(site_url('terima-kasih'))->with('nama', $data['nama_lengkap']);
    }

    /** Halaman form revisi (terisi data lama) — diakses lewat tautan token. */
    public function edit(string $token)
    {
        $model = new SubmissionModel();
        $row   = $model->where('edit_token', $token)->first();

        if (! $row || $row['status'] !== 'ditolak') {
            return view('public/revisi_invalid', [
                'title'   => 'Tautan Revisi',
                'setting' => $this->settings->get(),
            ]);
        }

        return view('public/form', [
            'title'     => 'Perbaiki Data Kesediaan',
            'setting'   => $this->settings->get(),
            'data'      => SubmissionModel::decode($row),
            'editToken' => $token,
            'adminNote' => $row['catatan_admin'],
        ]);
    }

    /** Proses penyimpanan revisi. */
    public function updateSubmission(string $token)
    {
        $model = new SubmissionModel();
        $row   = $model->where('edit_token', $token)->first();

        if (! $row || $row['status'] !== 'ditolak') {
            return view('public/revisi_invalid', [
                'title'   => 'Tautan Revisi',
                'setting' => $this->settings->get(),
            ]);
        }

        $bersedia = $this->request->getPost('bersedia');
        $rules = [
            'nama_lengkap' => 'required|max_length[150]',
            'bersedia'     => 'required|in_list[ya,tidak]',
        ];
        if ($bersedia === 'ya') {
            $rules['nomor_hp']           = 'required|max_length[30]';
            $rules['status_kepegawaian'] = 'required|in_list[PNS,PPPK,GTY,GTT]';
            $rules['komitmen_setuju']    = 'required';
        }

        if (! $this->validate($rules, $this->validationMessages())) {
            return redirect()->to(site_url('revisi/' . $token))->withInput()->with('errors', $this->validator->getErrors());
        }

        $data               = $this->buildData($this->request->getPost());
        $data['status']     = 'baru';   // kembali ke antrian untuk ditinjau ulang
        $data['edit_token'] = null;     // tautan revisi hanya berlaku sekali

        $model->update($row['id'], $data);

        return redirect()->to(site_url('terima-kasih'))
            ->with('nama', $data['nama_lengkap'])
            ->with('revisi', 1);
    }

    /** Halaman terima kasih. */
    public function success()
    {
        return view('public/success', [
            'title'   => 'Terima Kasih',
            'setting' => $this->settings->get(),
        ]);
    }

    // ====================================================================
    // Helper
    // ====================================================================

    /** Pesan validasi yang dipakai bersama (submit & revisi). */
    private function validationMessages(): array
    {
        return [
            'bersedia' => [
                'required' => 'Silakan pilih kesediaan Anda terlebih dahulu.',
                'in_list'  => 'Pilihan kesediaan tidak valid.',
            ],
            'komitmen_setuju' => [
                'required' => 'Anda harus menyetujui pernyataan kesediaan & komitmen.',
            ],
        ];
    }

    /** Susun array data kesediaan dari input POST (dipakai submit & revisi). */
    private function buildData(array $post): array
    {
        // Mata pelajaran yang diampu (tanpa jam)
        $mapel = [];
        foreach (($post['mapel'] ?? []) as $i => $nm) {
            $nm = trim((string) $nm);
            if ($nm === '') {
                continue;
            }
            $mapel[] = [
                'mapel' => $nm,
                'kelas' => trim((string) ($post['kelas'][$i] ?? '')),
            ];
        }

        // Ketersediaan hari -> sesi (Pagi/Siang)
        $hari = [];
        foreach (($post['hari'] ?? []) as $h => $sesi) {
            $pilih = array_values(array_filter((array) $sesi, static fn ($s) => in_array($s, ['Pagi', 'Siang'], true)));
            if ($pilih) {
                $hari[$h] = $pilih;
            }
        }

        $bersedia      = ($post['bersedia'] ?? '') === 'ya';
        $tugasBersedia = ! empty($post['tugas_bersedia']);

        return [
            'nama_lengkap'        => trim($post['nama_lengkap'] ?? ''),
            'nip_nuptk'           => null,
            'tempat_lahir'        => null,
            'tanggal_lahir'       => null,
            'pendidikan_terakhir' => trim($post['pendidikan_terakhir'] ?? ''),
            'guru_mapel'          => trim($post['guru_mapel'] ?? ''),
            'status_kepegawaian'  => ($post['status_kepegawaian'] ?? '') ?: null,
            'nomor_hp'            => trim($post['nomor_hp'] ?? ''),
            'mapel_diampu'        => json_encode($mapel, JSON_UNESCAPED_UNICODE),
            'total_jam'           => 0,
            'tugas_tambahan'      => json_encode($tugasBersedia ? ['Bersedia menerima tugas tambahan'] : [], JSON_UNESCAPED_UNICODE),
            'tugas_lainnya'       => null,
            'kesediaan_jam'       => json_encode(array_values($post['jam_kesediaan'] ?? []), JSON_UNESCAPED_UNICODE),
            'preferensi'          => json_encode([], JSON_UNESCAPED_UNICODE),
            'ketersediaan_hari'   => json_encode($hari, JSON_UNESCAPED_UNICODE),
            'keterangan_tambahan' => trim($post['keterangan_tambahan'] ?? ''),
            'bersedia_mengajar'   => $bersedia ? 1 : 0,
            'komitmen_setuju'     => ($bersedia && ! empty($post['komitmen_setuju'])) ? 1 : 0,
        ];
    }
}
