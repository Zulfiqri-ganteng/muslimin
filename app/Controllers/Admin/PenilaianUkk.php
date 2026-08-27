<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
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

    /** Simpan (insert/update) satu sel nilai peserta x penguji, lalu perbarui agregat peserta. */
    public function simpan($id)
    {
        $id     = (int) $id;
        $jadwal = $this->jadwalModel->find($id);
        if (! $jadwal) {
            return redirect()->to(site_url('admin/penilaian-ukk'))->with('error', 'Jadwal tidak ditemukan.');
        }
        $backUrl = site_url('admin/penilaian-ukk/jadwal/' . $id);

        $pesertaId = (int) $this->request->getPost('peserta_ukk_id');
        $tipe      = strtolower(trim((string) $this->request->getPost('tipe_penguji')));
        $guruId    = $tipe === 'internal' ? ((int) $this->request->getPost('guru_id') ?: null) : null;
        $eksId     = $tipe === 'eksternal' ? ((int) $this->request->getPost('penguji_eksternal_id') ?: null) : null;

        $pesertaModel = new PesertaUkkModel();
        $peserta = $pesertaModel->find($pesertaId);
        if (! $peserta || (int) $peserta['jadwal_ukk_id'] !== $id) {
            return redirect()->to($backUrl)->with('error', 'Peserta tidak valid untuk jadwal ini.');
        }
        if (! in_array($tipe, ['internal', 'eksternal'], true)) {
            return redirect()->to($backUrl)->with('error', 'Tipe penguji tidak valid.');
        }

        // Pastikan penguji memang ditugaskan pada jadwal ini (anti tamper).
        $tugas = (new JadwalUkkPengujiModel())->where('jadwal_ukk_id', $id)->where('tipe', $tipe);
        $tugas = $tipe === 'internal' ? $tugas->where('guru_id', $guruId) : $tugas->where('penguji_eksternal_id', $eksId);
        if ($tugas->countAllResults() === 0) {
            return redirect()->to($backUrl)->with('error', 'Penguji tidak ditugaskan pada jadwal ini.');
        }

        $angka = function (string $key): ?float {
            $v = trim((string) $this->request->getPost($key));

            return $v === '' ? null : (float) $v;
        };
        $skor = [
            'persiapan_skor' => $angka('persiapan_skor'),
            'proses_skor'    => $angka('proses_skor'),
            'hasil_skor'     => $angka('hasil_skor'),
            'sikap_skor'     => $angka('sikap_skor'),
            'waktu_skor'     => $angka('waktu_skor'),
        ];

        $paket      = (new PaketSoalUkkModel())->find($jadwal['paket_soal_id']);
        $nilaiModel = new NilaiUkkModel();
        $data = array_merge($skor, [
            'peserta_ukk_id'       => $pesertaId,
            'tipe_penguji'         => $tipe,
            'guru_id'              => $guruId,
            'penguji_eksternal_id' => $eksId,
            'nilai_akhir'          => $nilaiModel->hitungNilaiAkhir($skor, $paket ?: []),
            'tanggal_nilai'        => trim((string) $this->request->getPost('tanggal_nilai')) ?: date('Y-m-d'),
            'keterangan'           => trim((string) $this->request->getPost('keterangan')) ?: null,
        ]);

        $existingQ = $nilaiModel->withDeleted()->where('peserta_ukk_id', $pesertaId)->where('tipe_penguji', $tipe);
        $existingQ = $tipe === 'internal' ? $existingQ->where('guru_id', $guruId) : $existingQ->where('penguji_eksternal_id', $eksId);
        $existing  = $existingQ->first();

        $db = db_connect();
        $db->transException(true);
        $db->transStart();

        if ($existing) {
            $nilaiModel->protect(false);
            $ok = $nilaiModel->update($existing['id'], $data + ['deleted_at' => null]);
            $nilaiModel->protect(true);
            $recordId = $existing['id'];
        } else {
            $ok       = $nilaiModel->insert($data) !== false;
            $recordId = $nilaiModel->getInsertID();
        }

        if (! $ok) {
            $db->transRollback();

            return redirect()->to($backUrl)->with('error', implode(' ', $nilaiModel->errors()));
        }

        // Perbarui agregat nilai_akhir peserta + status kelulusan (vs KKM paket).
        $rata   = $nilaiModel->rataRataUntukPeserta($pesertaId);
        $update = ['nilai_akhir' => $rata];
        if ($rata !== null && $paket) {
            $lulus = $rata >= (float) $paket['kkm'];
            $update['status']   = $lulus ? 'lulus' : 'tidak_lulus';
            $update['predikat'] = $lulus ? 'Kompeten' : 'Belum Kompeten';
        }
        $pesertaModel->update($pesertaId, $update);
        $db->transComplete();

        master_data_changed('peserta_ukk');
        $this->audit->record($existing ? 'update' : 'create', 'nilai_ukk', $recordId, 'Simpan nilai UKK peserta #' . $pesertaId . ' (' . $tipe . ')');

        return redirect()->to($backUrl)->with('success', 'Nilai tersimpan.');
    }
}
