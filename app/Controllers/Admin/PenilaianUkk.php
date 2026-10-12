<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\UkkAlur;
use App\Models\AuditModel;
use App\Models\JadwalUkkModel;
use App\Models\JadwalUkkPengujiModel;
use App\Models\NilaiUkkModel;
use App\Models\PaketSoalUkkModel;
use App\Models\PesertaUkkModel;

/**
 * Penilaian UKK: matriks peserta x penguji per jadwal.
 *
 * Tiap penguji (internal/eksternal) yang ditugaskan pada sebuah jadwal
 * (lihat `Admin\JadwalUkk::penguji`) mengisi nilai sendiri per peserta.
 * Nilai akhir per baris dihitung berbobot (lihat `NilaiUkkModel::hitungNilaiAkhir`)
 * memakai bobot milik paket soal. Setelah tersimpan, nilai_akhir peserta
 * = rata-rata seluruh penguji, dan status lulus/tidak_lulus mengikuti KKM
 * paket soal.
 */
class PenilaianUkk extends BaseController
{
    protected JadwalUkkModel $jadwalModel;
    protected AuditModel $audit;

    public function __construct()
    {
        $this->jadwalModel = new JadwalUkkModel();
        $this->audit = new AuditModel();
    }

    /** Daftar jadwal UKK sebagai pintu masuk penilaian, + progres nilai. */
    public function index()
    {
        $rows = $this->jadwalModel->withRelations()->orderBy('jadwal_ukk.tanggal_mulai', 'DESC')->findAll();

        $pesertaModel   = new PesertaUkkModel();
        $totalPerJadwal = [];
        $dinilaiPerJadwal = [];
        foreach ($pesertaModel->select('jadwal_ukk_id, COUNT(*) AS jumlah')
            ->where('jadwal_ukk_id IS NOT NULL')->groupBy('jadwal_ukk_id')->findAll() as $r) {
            $totalPerJadwal[(int) $r['jadwal_ukk_id']] = (int) $r['jumlah'];
        }
        foreach ($pesertaModel->select('jadwal_ukk_id, COUNT(*) AS jumlah')
            ->where('jadwal_ukk_id IS NOT NULL')->where('nilai_akhir IS NOT NULL')
            ->groupBy('jadwal_ukk_id')->findAll() as $r) {
            $dinilaiPerJadwal[(int) $r['jadwal_ukk_id']] = (int) $r['jumlah'];
        }

        return view('admin/penilaian_ukk/index', [
            'title'            => 'Penilaian UKK',
            'rows'             => $rows,
            'totalPerJadwal'   => $totalPerJadwal,
            'dinilaiPerJadwal' => $dinilaiPerJadwal,
        ]);
    }

    /** Matriks nilai: peserta (baris) x penguji tertugas (kolom), untuk satu jadwal. */
    public function jadwal($id)
    {
        $id     = (int) $id;
        $jadwal = $this->jadwalModel->withRelations()->where('jadwal_ukk.id', $id)->first();
        if (! $jadwal) {
            return redirect()->to(site_url('admin/penilaian-ukk'))->with('error', 'Jadwal tidak ditemukan.');
        }
        $paket = (new PaketSoalUkkModel())->find($jadwal['paket_soal_id']);

        $peserta = (new PesertaUkkModel())->withRelations()
            ->where('peserta_ukk.jadwal_ukk_id', $id)->orderBy('siswa.nama', 'ASC')->findAll();
        $pengujiList = (new JadwalUkkPengujiModel())->forJadwal($id);

        $nilaiMap = [];
        $pesertaIds = array_column($peserta, 'id');
        if ($pesertaIds !== []) {
            foreach ((new NilaiUkkModel())->whereIn('peserta_ukk_id', $pesertaIds)->findAll() as $n) {
                $pengujiKey = $n['tipe_penguji'] === 'internal' ? $n['guru_id'] : $n['penguji_eksternal_id'];
                $nilaiMap[$n['peserta_ukk_id'] . '-' . $n['tipe_penguji'] . '-' . $pengujiKey] = $n;
            }
        }

        return view('admin/penilaian_ukk/jadwal', [
            'title'       => 'Penilaian — ' . ($jadwal['paket_nama'] ?? 'Jadwal UKK'),
            'jadwal'      => $jadwal,
            'paket'       => $paket,
            'peserta'     => $peserta,
            'pengujiList' => $pengujiList,
            'nilaiMap'    => $nilaiMap,
        ]);
    }

    /**
     * Simpan (insert/update) satu sel nilai peserta x penguji, lalu perbarui agregat peserta.
     * Aturannya (anti tamper, bobot, KKM) bersama API di UkkAlur::simpanNilai.
     */
    public function simpan($id)
    {
        $id    = (int) $id;
        $hasil = UkkAlur::simpanNilai($id, (array) $this->request->getPost());
        if (! $hasil['ok']) {
            return redirect()->to(site_url($hasil['kode'] === 404 ? 'admin/penilaian-ukk' : 'admin/penilaian-ukk/jadwal/' . $id))
                ->with('error', $hasil['pesan']);
        }

        $this->audit->record($hasil['baru'] ? 'create' : 'update', 'nilai_ukk', $hasil['nilai_id'], 'Simpan nilai UKK peserta #' . $hasil['peserta_id'] . ' (' . $hasil['tipe'] . ')');

        return redirect()->to(site_url('admin/penilaian-ukk/jadwal/' . $id))->with('success', 'Nilai tersimpan.');
    }
}
