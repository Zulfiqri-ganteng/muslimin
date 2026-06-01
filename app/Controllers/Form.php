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

    /** Proses pengiriman form. */
    public function submit()
    {
        $setting = $this->settings->get();
        if (empty($setting['form_open'])) {
            return redirect()->to(site_url('tutup'));
        }

        $rules = [
            'nama_lengkap'       => 'required|max_length[150]',
            'nip_nuptk'          => 'required|max_length[60]|is_unique[submissions.nip_nuptk]',
            'nomor_hp'           => 'required|max_length[30]',
            'status_kepegawaian' => 'required|in_list[PNS,PPPK,GTY,GTT]',
            'komitmen_setuju'    => 'required',
        ];
        $messages = [
            'nip_nuptk' => [
                'is_unique' => 'NIP/NUPTK ini sudah pernah mengisi formulir. Satu NIP/NUPTK hanya dapat mengisi satu kali. Hubungi admin bila perlu mengubah data.',
                'required'  => 'NIP/NUPTK wajib diisi.',
            ],
            'komitmen_setuju' => [
                'required' => 'Anda harus menyetujui pernyataan kesediaan & komitmen.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $post = $this->request->getPost();

        // --- Mata pelajaran yang diampu ---
        $mapel = [];
        $total = 0;
        $mNames = $post['mapel'] ?? [];
        $kelas  = $post['kelas'] ?? [];
        $jam    = $post['jam'] ?? [];
        foreach ($mNames as $i => $nm) {
            $nm = trim((string) $nm);
            if ($nm === '') {
                continue;
            }
            $j       = (int) ($jam[$i] ?? 0);
            $total  += $j;
            $mapel[] = [
                'mapel' => $nm,
                'kelas' => trim((string) ($kelas[$i] ?? '')),
                'jam'   => $j,
            ];
        }

        // --- Preferensi mata pelajaran ---
        $pref = [];
        foreach (($post['pref'] ?? []) as $prio => $val) {
            $val = trim((string) $val);
            if ($val !== '') {
                $pref[] = ['prioritas' => (int) $prio, 'mapel' => $val];
            }
        }

        // --- Ketersediaan hari ---
        $hari = [];
        foreach (($post['hari'] ?? []) as $h => $v) {
            $hari[$h] = $v;
        }

        $data = [
            'nama_lengkap'        => trim($post['nama_lengkap']),
            'nip_nuptk'           => trim($post['nip_nuptk']),
            'tempat_lahir'        => trim($post['tempat_lahir'] ?? ''),
            'tanggal_lahir'       => ($post['tanggal_lahir'] ?? '') ?: null,
            'pendidikan_terakhir' => trim($post['pendidikan_terakhir'] ?? ''),
            'guru_mapel'          => trim($post['guru_mapel'] ?? ''),
            'status_kepegawaian'  => $post['status_kepegawaian'],
            'nomor_hp'            => trim($post['nomor_hp']),
            'mapel_diampu'        => json_encode($mapel, JSON_UNESCAPED_UNICODE),
            'total_jam'           => $total,
            'tugas_tambahan'      => json_encode(array_values($post['tugas'] ?? []), JSON_UNESCAPED_UNICODE),
            'tugas_lainnya'       => trim($post['tugas_lainnya'] ?? ''),
            'kesediaan_jam'       => json_encode(array_values($post['jam_kesediaan'] ?? []), JSON_UNESCAPED_UNICODE),
            'preferensi'          => json_encode($pref, JSON_UNESCAPED_UNICODE),
            'ketersediaan_hari'   => json_encode($hari, JSON_UNESCAPED_UNICODE),
            'keterangan_tambahan' => trim($post['keterangan_tambahan'] ?? ''),
            'bersedia_mengajar'   => 1,
            'komitmen_setuju'     => 1,
            'status'              => 'baru',
            'ip_address'          => $this->request->getIPAddress(),
        ];

        $model = new SubmissionModel();
        $model->insert($data);

        return redirect()->to(site_url('terima-kasih'))->with('nama', $data['nama_lengkap']);
    }

    /** Halaman terima kasih. */
    public function success()
    {
        return view('public/success', [
            'title'   => 'Terima Kasih',
            'setting' => $this->settings->get(),
        ]);
    }
}
