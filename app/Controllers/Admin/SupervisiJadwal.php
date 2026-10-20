<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\Supervisi;

/**
 * Jadwal Supervisi (menu KURIKULUM → Supervisi Guru): kunjungan supervisi per semester — saring, tambah/ubah/batalkan/hapus, buat
 * massal (banyak guru dibagi rata ke hari sekolah). Saran mapel & kelas dari Penugasan guru (JSON). Logika: Libraries\Supervisi.
 */
class SupervisiJadwal extends BaseController
{
    public function index()
    {
        $sup    = new Supervisi();
        $saring = Supervisi::saringan($this->request->getGet() ?? []);

        return view('admin/supervisi/jadwal', [
            'title'      => 'Jadwal Supervisi',
            'saring'     => $saring,
            'rows'       => $sup->daftarJadwal($saring),
            'ringkas'    => $sup->ringkasan($saring['ta'], $saring['semester']),
            'pilihanTa'  => $sup->pilihanTahunAjaran(),
            'guru'       => $sup->pilihanGuru(),
            'supervisor' => $sup->pilihanSupervisor(),
            'mapel'      => $sup->pilihanMapel(),
            'kelas'      => $sup->pilihanKelas(),
            'instrumen'  => $sup->daftarInstrumen(),
            'kelola'     => HakAkses::boleh((string) (session('admin')['role'] ?? ''), 'admin/supervisi/jadwal', 'POST'),
        ]);
    }

    /** GET saran?guru_id= → JSON mapel & kelas yang diampu guru itu (Penugasan), JP terbanyak dulu. */
    public function saran()
    {
        return $this->response->setJSON(['ok' => true, 'saran' => (new Supervisi())->saranPengampu((int) $this->request->getGet('guru_id'))]);
    }

    public function store()
    {
        $r = (new Supervisi())->simpanJadwal($this->isian(), $this->pencatat());

        return $this->kembali($r, ! $r['ok']);
    }

    public function update($id)
    {
        return $this->kembali((new Supervisi())->simpanJadwal($this->isian(), $this->pencatat(), (int) $id));
    }

    /** POST massal — guru_ids[], supervisor_id, mulai, selesai, per_hari, jam, instrumen[]. */
    public function massal()
    {
        $r = (new Supervisi())->buatMassal($this->request->getPost(['guru_ids', 'supervisor_id', 'mulai', 'selesai', 'per_hari', 'jam', 'instrumen']), $this->pencatat());

        return $this->kembali($r);
    }

    public function delete($id)
    {
        return $this->kembali((new Supervisi())->hapusJadwal((int) $id));
    }

    private function isian(): array
    {
        return $this->request->getPost(['guru_id', 'supervisor_id', 'tanggal', 'jam', 'mapel_id', 'kelas_id', 'instrumen', 'catatan', 'batal']);
    }

    /** @return array{id: ?int, nama: string} */
    private function pencatat(): array
    {
        $a = session('admin') ?? [];

        return ['id' => isset($a['id']) ? (int) $a['id'] : null, 'nama' => (string) ($a['full_name'] ?? '')];
    }

    /**
     * Kembali ke daftar dengan saringan yang sedang dipakai (dikirim formulir sebagai 'kembali' = query string). Jadwal baru di
     * semester lain → saringan dipindah ke semester jadwal itu agar langsung terlihat.
     */
    private function kembali(array $r, bool $denganIsian = false)
    {
        $qs = (string) $this->request->getPost('kembali');
        parse_str(ltrim($qs, '?'), $get);
        $tanggal = Supervisi::tanggalSah((string) ($this->request->getPost('tanggal') ?? $this->request->getPost('mulai') ?? ''));
        if ($r['ok'] && $tanggal !== null) {
            $p   = Supervisi::periode($tanggal);
            $get = ['ta' => $p['tahun_ajaran'], 'semester' => $p['semester']] + array_diff_key($get, ['ta' => 1, 'semester' => 1]);
        }
        $ke = redirect()->to(site_url('admin/supervisi/jadwal') . ($get !== [] ? '?' . http_build_query($get) : ''));
        if ($denganIsian) {
            $ke = $ke->withInput();
        }

        return $ke->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }
}
