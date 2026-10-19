<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\HakAkses;
use App\Libraries\HonorCetak;
use App\Libraries\Keterlambatan as Buku;
use App\Libraries\KeterlambatanExcel;
use App\Libraries\KeterlambatanWa;
use App\Models\AuditModel;
use App\Models\SettingModel;

/**
 * Catat Keterlambatan (menu KURIKULUM → Guru Piket): buku piket siswa terlambat per tanggal — catat cepat (cari siswa, kelas
 * otomatis; siswa yang belum terdaftar di Data Siswa ditulis manual), daftar hari itu, ubah/hapus, tautkan catatan manual ke Data
 * Siswa. Dipakai akun Guru Piket (akun bersama di meja piket) dan Admin. Logika: Libraries\Keterlambatan.
 * Guru piket yang terakhir dipilih diingat di sesi SELAMA HARI ITU (berganti hari → wajib dipilih lagi).
 */
class Keterlambatan extends BaseController
{
    private const SESI_GURU = 'keterlambatan_guru';

    public function index()
    {
        $buku    = new Buku();
        $tanggal = Buku::tanggalSah((string) $this->request->getGet('tanggal')) ?? date('Y-m-d');
        $ingat   = session(self::SESI_GURU);
        $peran   = (string) (session('admin')['role'] ?? '');
        $templat = KeterlambatanWa::templat();
        $sekolah = (string) ((new SettingModel())->get()['school_name'] ?? '');
        $rows    = $buku->daftar($tanggal);
        foreach ($rows as &$r) {
            $r['kabar'] = KeterlambatanWa::kabar($r, $templat, $sekolah);
        }
        unset($r);

        return view('admin/keterlambatan/index', [
            'title'        => 'Catat Keterlambatan',
            'tanggal'      => $tanggal,
            'hariIni'      => $tanggal === date('Y-m-d'),
            'rows'         => $rows,
            'bolehKabari'  => HakAkses::boleh($peran, 'admin/keterlambatan/0/kabari', 'POST'),
            'bolehPesan'   => HakAkses::boleh($peran, 'admin/keterlambatan/pesan', 'POST') && KeterlambatanWa::kolomAda(),
            'bolehTautkan' => HakAkses::boleh($peran, 'admin/keterlambatan/0/tautkan', 'POST'),
            'templatWa'    => $templat,
            'piket'        => $buku->guruPiketHari($tanggal),
            'guru'         => $buku->pilihanGuru(),
            'kelas'        => $buku->pilihanKelas(),
            'saranSanksi'  => $buku->saranSanksi(),
            'guruIngat'    => is_array($ingat) && ($ingat['tanggal'] ?? '') === date('Y-m-d') ? (int) ($ingat['id'] ?? 0) : 0,
        ]);
    }

    /**
     * GET ?q=&tanggal=[&manual=0] → JSON siswa aktif yang cocok + (bawaan) saran siswa belum terdaftar yang pernah dicatat manual
     * (kotak cari formulir catat). manual=0: hanya Data Siswa (kotak cari dialog Tautkan).
     */
    public function siswa()
    {
        $tanggal = Buku::tanggalSah((string) $this->request->getGet('tanggal')) ?? date('Y-m-d');

        return $this->response->setJSON([
            'ok'    => true,
            'hasil' => (new Buku())->cariSiswa((string) $this->request->getGet('q'), $tanggal, 15, $this->request->getGet('manual') !== '0'),
        ]);
    }

    public function store()
    {
        $data = $this->request->getPost(['siswa_id', 'tanggal', 'jam', 'guru_id', 'sanksi', 'keterangan', 'manual', 'nama_siswa', 'kelas_id', 'hp_ortu']);
        $r    = (new Buku())->catat($data, $this->pencatat());
        $ke   = $this->alamat((string) ($data['tanggal'] ?? ''));
        if (! $r['ok']) {
            return redirect()->to($ke)->withInput()->with('error', $r['pesan']);
        }
        session()->set(self::SESI_GURU, ['tanggal' => date('Y-m-d'), 'id' => (int) $data['guru_id']]);

        return redirect()->to($ke)->with('success', $r['pesan']);
    }

    public function update($id)
    {
        $buku = new Buku();
        $row  = $buku->cari((int) $id);
        // nama_siswa/kelas_id/hp_ortu hanya dipakai untuk catatan siswa belum terdaftar (dialog Ubah menampilkannya hanya untuk itu).
        $r    = $buku->ubah((int) $id, $this->request->getPost(['jam', 'guru_id', 'sanksi', 'keterangan', 'nama_siswa', 'kelas_id', 'hp_ortu']));

        return redirect()->to($this->alamat((string) ($row['tanggal'] ?? '')))->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    /** POST {id}/tautkan — {siswa_id, semua}: catatan siswa belum terdaftar → siswa di Data Siswa. */
    public function tautkan($id)
    {
        $buku = new Buku();
        $row  = $buku->cari((int) $id);
        $r    = $buku->tautkan((int) $id, (int) $this->request->getPost('siswa_id'), $this->request->getPost('semua') === '1');

        return redirect()->to($this->alamat((string) ($row['tanggal'] ?? '')))->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    public function delete($id)
    {
        $r = (new Buku())->hapus((int) $id);

        return redirect()->to($this->alamat((string) ($r['tanggal'] ?? '')))->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    /** POST {id}/kabari — guru piket menekan tombol WhatsApp orang tua (JSON untuk pkl-kabar.js). */
    public function kabari($id)
    {
        $r = (new KeterlambatanWa())->tandai((int) $id, $this->pencatat()['nama']);

        return $this->response->setStatusCode($r['ok'] ? 200 : 404)->setJSON($r['ok']
            ? ['ok' => true, 'waktu' => $r['waktu'], 'oleh' => $r['oleh'], 'csrf' => csrf_hash()]
            : ['ok' => false, 'message' => $r['pesan'], 'csrf' => csrf_hash()]);
    }

    /** POST {id}/hp — isi nomor HP orang tua ke Data Siswa (hanya kolom yang masih kosong). */
    public function isiHp($id)
    {
        $buku = new Buku();
        $row  = $buku->cari((int) $id);
        $r    = (new KeterlambatanWa())->isiHp((int) $id, (string) $this->request->getPost('hp'));

        return redirect()->to($this->alamat((string) ($row['tanggal'] ?? '')))->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    /** POST pesan — templat pesan WhatsApp ke orang tua (khusus Admin, lihat MenuAdmin 'keterlambatan_pesan'). */
    public function pesan()
    {
        $galat = KeterlambatanWa::simpanTemplat((string) $this->request->getPost('wa_pesan'));

        return redirect()->to($this->alamat((string) $this->request->getPost('tanggal')))
            ->with($galat === null ? 'success' : 'error', $galat ?? 'Pesan WhatsApp ke orang tua disimpan.');
    }

    /** GET ?tanggal= → Excel buku piket hari itu (format sekolah; tetap ada lembar walau belum ada catatan). */
    public function excel()
    {
        $tanggal = Buku::tanggalSah((string) $this->request->getGet('tanggal')) ?? date('Y-m-d');
        $bahan   = (new Buku())->bahanBuku($tanggal, $tanggal);
        (new AuditModel())->record('export', 'keterlambatan_siswa', null, 'Unduh Excel buku piket ' . $tanggal . ' (' . count($bahan[0]['rows']) . ' siswa)');

        return $this->unduh(KeterlambatanExcel::bukuPiket($bahan), KeterlambatanExcel::namaBerkasHari($tanggal));
    }

    /** Rekap Keterlambatan: ?mulai=&selesai=&kelas=&tampil=siswa|kelas. */
    public function rekap()
    {
        $buku   = new Buku();
        $r      = Buku::rentangSah((string) $this->request->getGet('mulai'), (string) $this->request->getGet('selesai'));
        $kelas  = $buku->pilihanKelas();
        $kelasId = $this->kelasDipilih($kelas);

        return view('admin/keterlambatan/rekap', [
            'title'   => 'Rekap Keterlambatan',
            'mulai'   => $r['mulai'],
            'selesai' => $r['selesai'],
            'pesan'   => $r['pesan'],
            'kelas'   => $kelas,
            'kelasId' => $kelasId,
            'tampil'  => $this->request->getGet('tampil') === 'kelas' ? 'kelas' : 'siswa',
            'rekap'   => $buku->rekap($r['mulai'], $r['selesai'], $kelasId),
        ]);
    }

    /** GET rekap/excel?mulai=&selesai=&kelas= → Excel rekap per siswa & per kelas. */
    public function rekapExcel()
    {
        $buku    = new Buku();
        $r       = Buku::rentangSah((string) $this->request->getGet('mulai'), (string) $this->request->getGet('selesai'));
        $kelas   = $buku->pilihanKelas();
        $kelasId = $this->kelasDipilih($kelas);
        $rekap   = $buku->rekap($r['mulai'], $r['selesai'], $kelasId);
        (new AuditModel())->record('export', 'keterlambatan_siswa', null, 'Unduh rekap keterlambatan ' . $r['mulai'] . ' s.d. ' . $r['selesai'] . ' (' . $rekap['total'] . ' catatan)');

        return $this->unduh(KeterlambatanExcel::rekap($rekap, $r['mulai'], $r['selesai'], $this->namaKelas($kelas, $kelasId)), KeterlambatanExcel::namaBerkasRekap($r['mulai'], $r['selesai']));
    }

    /** GET rekap/buku?mulai=&selesai=&kelas= → Excel buku piket rentang (satu lembar per hari yang ada catatannya). */
    public function rekapBuku()
    {
        $buku    = new Buku();
        $r       = Buku::rentangSah((string) $this->request->getGet('mulai'), (string) $this->request->getGet('selesai'));
        $kelasId = $this->kelasDipilih($buku->pilihanKelas());
        $bahan   = $buku->bahanBuku($r['mulai'], $r['selesai'], $kelasId);
        if ($bahan === []) {
            return redirect()->to(site_url('admin/keterlambatan/rekap') . '?' . http_build_query(['mulai' => $r['mulai'], 'selesai' => $r['selesai'], 'kelas' => $kelasId ?: null]))
                ->with('error', 'Tidak ada catatan keterlambatan pada rentang itu — tidak ada yang diunduh.');
        }
        (new AuditModel())->record('export', 'keterlambatan_siswa', null, 'Unduh Excel buku piket ' . $r['mulai'] . ' s.d. ' . $r['selesai'] . ' (' . count($bahan) . ' hari)');

        return $this->unduh(KeterlambatanExcel::bukuPiket($bahan), KeterlambatanExcel::namaBerkasRentang($r['mulai'], $r['selesai']));
    }

    private function unduh(\PhpOffice\PhpSpreadsheet\Spreadsheet $ss, string $nama)
    {
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nama . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(HonorCetak::xlsx($ss));
    }

    /** @param list<array{id: int, nama: string}> $kelas */
    private function kelasDipilih(array $kelas): int
    {
        $id = (int) $this->request->getGet('kelas');

        return in_array($id, array_column($kelas, 'id'), true) ? $id : 0;
    }

    /** @param list<array{id: int, nama: string}> $kelas */
    private function namaKelas(array $kelas, int $id): string
    {
        foreach ($kelas as $k) {
            if ($k['id'] === $id) {
                return $k['nama'];
            }
        }

        return '';
    }

    /** @return array{id: ?int, nama: string} */
    private function pencatat(): array
    {
        $a = session('admin') ?? [];

        return ['id' => isset($a['id']) ? (int) $a['id'] : null, 'nama' => (string) ($a['full_name'] ?? '')];
    }

    /** Kembali ke daftar tanggal itu (hari ini bila tanggal kosong/tidak sah). */
    private function alamat(string $tanggal): string
    {
        $t = Buku::tanggalSah($tanggal);

        return site_url('admin/keterlambatan') . ($t !== null && $t !== date('Y-m-d') ? '?tanggal=' . $t : '');
    }
}
