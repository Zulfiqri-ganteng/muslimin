<?php

namespace App\Controllers\Admin;

use App\Libraries\HakAkses;
use App\Libraries\PklHak;
use App\Libraries\SuratAcara;
use App\Libraries\SuratJadwal as Lib;
use App\Libraries\SuratJenis;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * SURAT SEKOLAH → JADWAL ASTS & TKA (Layanan Siswa, 2026-10-21): jadwal kegiatan yang dibuka untuk permintaan surat izin dari
 * siswa. Tiap jenis dikelola pemegang hak Buat jenis itu (bawaan Operator). Tanpa Alpine: tambah/ubah memakai <details>, hapus
 * memakai data-confirm (app.js). Aturan & penyimpanan: Libraries\SuratJadwal.
 *
 * Alamat: GET admin/surat/jadwal · POST admin/surat/jadwal (tambah, isian `jenis`) · POST …/{id} (ubah) · POST …/{id}/buka ·
 * POST …/{id}/tutup · POST …/{id}/hapus.
 */
class SuratJadwal extends SuratDasar
{
    private const ALAMAT = 'admin/surat/jadwal';

    public function index()
    {
        if (! Lib::tabelAda()) {
            return $this->ke('admin/surat', 'error', 'Fitur jadwal belum aktif di server ini: jalankan "php spark migrate".');
        }
        $jenis = $this->jenisBoleh();
        if ($jenis === []) {
            return $this->ke('admin/surat', 'error', 'Akun ' . HakAkses::label($this->peranSaya()) . ' tidak punya hak membuat Surat Izin Ujian / TKA, jadi tidak bisa mengatur jadwalnya. Admin bisa mengaturnya di Sistem → Hak Akses.');
        }
        $grup = array_fill_keys($jenis, []);
        foreach ((new Lib())->daftar($jenis) as $j) {
            $grup[$j['jenis']][] = $j;
        }

        return view('admin/surat/jadwal', $this->dasarSurat('Jadwal Ujian & TKA', 'jadwal') + [
            'grup'      => $grup,
            'periode'   => in_array(SuratJenis::ASTS, $jenis, true) ? (new SuratAcara())->periodeUjian() : [],
            'tahunAjar' => (string) ((new SettingModel())->get()['academic_year'] ?? ''),
            'galat'     => (array) (session()->getFlashdata('galat_jadwal') ?? []),
            'gagal'     => (string) (session()->getFlashdata('jadwal_gagal') ?? ''),
        ]);
    }

    public function tambah(): RedirectResponse
    {
        $jenis = (string) $this->request->getPost('jenis');
        if (! in_array($jenis, Lib::JENIS, true)) {
            return $this->ke(self::ALAMAT, 'error', 'Jenis jadwal tidak dikenal.');
        }
        if (($tolak = $this->tolakJenis('buat', $jenis, self::ALAMAT)) !== null) {
            return $tolak;
        }

        return $this->hasil((new Lib())->simpan($jenis, (array) $this->request->getPost(), null, $this->konteks(['saluran' => 'web'])), 'tambah-' . $jenis);
    }

    /** @param int|string $id */
    public function ubah($id): RedirectResponse
    {
        [$j, $tolak] = $this->muatBoleh((int) $id);
        if ($tolak !== null) {
            return $tolak;
        }

        return $this->hasil((new Lib())->simpan($j['jenis'], (array) $this->request->getPost(), $j['id'], $this->konteks(['saluran' => 'web'])), 'ubah-' . $j['id']);
    }

    /** @param int|string $id */
    public function buka($id): RedirectResponse
    {
        return $this->aturBuka((int) $id, true);
    }

    /** @param int|string $id */
    public function tutup($id): RedirectResponse
    {
        return $this->aturBuka((int) $id, false);
    }

    /** @param int|string $id */
    public function hapus($id): RedirectResponse
    {
        [$j, $tolak] = $this->muatBoleh((int) $id);
        if ($tolak !== null) {
            return $tolak;
        }
        $r = (new Lib())->hapus($j['id'], $this->konteks(['saluran' => 'web']));

        return $this->ke(self::ALAMAT, $r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    // ----- pembantu ---------------------------------------------------

    /** @return list<string> jenis jadwal yang boleh dikelola peran ini (hak Buat jenis itu) */
    private function jenisBoleh(): array
    {
        $peran = $this->peranSaya();

        return array_values(array_filter(Lib::JENIS, static fn (string $j): bool => PklHak::bolehSurat($peran, 'buat', $j)));
    }

    /** @return array{0: array<string, mixed>|null, 1: RedirectResponse|null} */
    private function muatBoleh(int $id): array
    {
        $j = (new Lib())->muat($id);
        if ($j === null) {
            return [null, $this->ke(self::ALAMAT, 'error', 'Jadwal tidak ditemukan (mungkin sudah dihapus).')];
        }

        return [$j, $this->tolakJenis('buat', $j['jenis'], self::ALAMAT)];
    }

    private function aturBuka(int $id, bool $buka): RedirectResponse
    {
        [$j, $tolak] = $this->muatBoleh($id);
        if ($tolak !== null) {
            return $tolak;
        }
        $r = (new Lib())->aturBuka($j['id'], $buka, $this->konteks(['saluran' => 'web']));

        return redirect()->to(site_url(self::ALAMAT) . '#jadwal-' . $j['id'])->with($r['ok'] ? 'success' : 'error', $r['pesan']);
    }

    /** @param array{ok: bool, pesan: string, galat?: array<string, string>, id?: int} $r */
    private function hasil(array $r, string $formulir): RedirectResponse
    {
        if ($r['ok']) {
            return redirect()->to(site_url(self::ALAMAT) . '#jadwal-' . (int) ($r['id'] ?? 0))->with('success', $r['pesan']);
        }

        return redirect()->to(site_url(self::ALAMAT) . '#' . $formulir)->withInput()
            ->with('error', $r['pesan'])->with('galat_jadwal', $r['galat'] ?? [])->with('jadwal_gagal', $formulir);
    }
}
