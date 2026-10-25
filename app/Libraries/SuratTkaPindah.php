<?php

namespace App\Libraries;

use App\Models\AuditModel;
use App\Models\PklPengaturanModel;
use App\Models\SuratSekolahModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Alat staf "Pindahkan siswa ke gelombang lain" untuk Surat Izin TKA per perusahaan (F2, keluhan klien 2026-10-25: teman satu
 * tempat PKL beda gelombang; 15 siswa tercantum di surat Gelombang 1 DAN 2). Dipakai pemegang hak ACC Izin TKA (bawaan Operator) di
 * detail surat web (Admin\Surat::pindahSiswa) dan API Android.
 *
 *   - PINDAH: siswa dikeluarkan dari surat ini lalu dimasukkan ke surat perusahaan yang sama untuk gelombang tujuan — surat yang
 *     masih menunggu dulu, lalu yang sudah disetujui (namanya ditambahkan); belum ada → surat BARU, langsung di-ACC atas nama staf
 *     bila surat asal sudah disetujui.
 *   - KELUARKAN saja (jadwal 0): untuk siswa yang sudah tercantum di surat gelombang yang benar (data ganda).
 *   - Surat yang sudah disetujui/bernomor BOLEH diubah lewat alat ini: nomor tetap, surat yang pernah diunduh bertanda "perlu cetak
 *     ulang". Surat yang jadi kosong dibatalkan (nomornya tetap tercatat). Baris siswa lain tidak disentuh (tanda WhatsApp tetap).
 *   - Permintaan siswa (surat_permintaan) ikut pindah supaya halaman status siswa menunjuk surat yang benar.
 * Semua dalam SATU transaksi: satu langkah gagal → tidak ada yang berubah.
 */
final class SuratTkaPindah
{
    private BaseConnection $db;
    private SuratSekolah $svc;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db  = $db ?? db_connect();
        $this->svc = new SuratSekolah($this->db);
    }

    /** Alat ini berlaku untuk surat ini? Surat Izin TKA per perusahaan yang belum dibatalkan. */
    public static function berlaku(array $surat): bool
    {
        $isi = is_array($surat['isi_arr'] ?? null) ? $surat['isi_arr'] : SuratSekolah::dekodeIsi((string) ($surat['isi'] ?? ''));

        return (string) ($surat['jenis'] ?? '') === SuratJenis::TKA && (string) ($surat['status'] ?? '') !== 'dibatalkan'
            && ($isi['mode'] ?? '') === 'perusahaan' && (string) ($isi['pkey'] ?? '') !== '';
    }

    /**
     * Gelombang tujuan: jadwal TKA (menu Jadwal Ujian & TKA) yang kegiatannya belum lewat, selain gelombang surat ini — dibuka
     * atau ditutup untuk siswa sama saja bagi staf.
     *
     * @return list<array<string, mixed>>
     */
    public function tujuan(array $surat): array
    {
        $isi = is_array($surat['isi_arr'] ?? null) ? $surat['isi_arr'] : SuratSekolah::dekodeIsi((string) ($surat['isi'] ?? ''));

        $out = array_values(array_filter((new SuratJadwal($this->db))->daftar([SuratJenis::TKA]),
            static fn (array $j): bool => $j['keadaan'] !== 'selesai' && ! LayananSiswa::cocokJadwal($isi, $j)));
        usort($out, static fn (array $a, array $b): int => strcmp((string) $a['tgl_mulai'], (string) $b['tgl_mulai']) ?: $a['id'] <=> $b['id']); // Gelombang 1, 2, 3…

        return $out;
    }

    /**
     * Surat Izin TKA LAIN (belum dibatalkan) yang juga memuat siswa surat ini — tanda data ganda (satu siswa di dua gelombang).
     *
     * @param list<int> $siswaIds
     *
     * @return array<int, list<array<string, mixed>>> siswa_id => penempatan (LayananSiswa::penempatanTka)
     */
    public function lainnya(int $suratId, array $siswaIds): array
    {
        $out = [];
        foreach ((new LayananSiswa($this->db))->penempatanTka($siswaIds) as $sid => $daftar) {
            $lain = array_values(array_filter($daftar, static fn (array $t): bool => $t['surat_id'] !== $suratId));
            if ($lain !== []) {
                $out[$sid] = $lain;
            }
        }

        return $out;
    }

    /**
     * Pindahkan siswa $siswaId dari surat $suratId ke gelombang $jadwalId (0 = keluarkan saja dari surat ini).
     *
     * kode: ok | tidak_ada (404) | galat (422) | gagal (409)
     *
     * @param array<string, mixed> $konteks ['oleh', 'admin_id', 'peran', 'ip', 'saluran']
     *
     * @return array{ok: bool, kode: string, pesan: string, tujuan_id?: int|null}
     */
    public function pindahkan(int $suratId, int $siswaId, int $jadwalId, string $catatan, array $konteks, ?array $p = null): array
    {
        $p ??= (new PklPengaturanModel())->ambil();
        $m   = $this->svc->muat($suratId);
        if ($m === null) {
            return ['ok' => false, 'kode' => 'tidak_ada', 'pesan' => 'Surat tidak ditemukan (mungkin sudah dihapus).'];
        }
        $s = $m['surat'];
        if (! self::berlaku($s)) {
            return ['ok' => false, 'kode' => 'galat', 'pesan' => 'Pindah gelombang hanya untuk Surat Izin TKA per perusahaan yang belum dibatalkan.'];
        }
        $baris = null;
        foreach ($m['siswa'] as $x) {
            $baris = (int) $x['siswa_id'] === $siswaId ? $x : $baris;
        }
        if ($baris === null) {
            return ['ok' => false, 'kode' => 'galat', 'pesan' => 'Siswa itu tidak tercantum di surat ini. Muat ulang halaman.'];
        }
        $catatan = IsianBantu::rapikan($catatan);
        if (mb_strlen($catatan) > 200) {
            return ['ok' => false, 'kode' => 'galat', 'pesan' => 'Catatan terlalu panjang (maksimal 200 huruf).'];
        }
        $isi   = $s['isi_arr'];
        $j     = null;
        if ($jadwalId > 0) {
            foreach ($this->tujuan($s) as $x) {
                $j = (int) $x['id'] === $jadwalId ? $x : $j;
            }
            if ($j === null) {
                return ['ok' => false, 'kode' => 'galat', 'pesan' => 'Gelombang tujuan tidak ditemukan atau kegiatannya sudah lewat. Pilih gelombang lain.'];
            }
        }
        $nama  = (string) $baris['nama'];
        $kode  = SuratSekolahModel::kode($suratId);
        $label = SuratAcara::namaPendek(SuratJenis::TKA, $isi);
        $row   = ['siswa_id' => $siswaId, 'kelas_id' => $baris['kelas_id'] ?? null, 'hp' => $baris['hp'] ?? null];
        $ulang = []; // kode surat yang perlu diunduh ulang
        $tujuanId = null;

        $this->db->transBegin();
        try {
            // 1) Masukkan ke surat gelombang tujuan (kalau pindah).
            if ($j !== null) {
                $pilih = null;
                foreach ((new LayananSiswa($this->db))->suratGelombang((string) $isi['pkey'], $j) as $d) {
                    $pilih ??= $d['bisa_ubah'] ? $d : null; // yang masih menunggu dulu
                }
                foreach ((new LayananSiswa($this->db))->suratGelombang((string) $isi['pkey'], $j) as $d) {
                    $pilih ??= $d; // lalu yang sudah disetujui
                }
                if ($pilih !== null) {
                    $tujuanId = $pilih['id'];
                    $mt       = $this->svc->muat($tujuanId);
                    $ada      = in_array($siswaId, array_map('intval', array_column($mt['siswa'] ?? [], 'siswa_id')), true);
                    if (! $ada) {
                        $jml = count($mt['siswa']) + 1;
                        if (! $this->svc->aturSiswa($tujuanId, null, $row, SuratAcara::judul(SuratJenis::TKA, $mt['surat']['isi_arr'], (string) $mt['surat']['perusahaan_nama'], $jml))) {
                            throw new \RuntimeException('tambah ke surat tujuan ' . $tujuanId);
                        }
                        $this->svc->catat($tujuanId, 'pindah', $konteks, $nama . ' dipindah ke surat ini dari ' . $kode . ' (' . $label . ')' . ($catatan !== '' ? ' — ' . $catatan : ''));
                        if ((int) $mt['surat']['cetak_ke'] > 0) {
                            $ulang[] = SuratSekolahModel::kode($tujuanId);
                        }
                    }
                } else {
                    $isiBaru = SuratJadwal::isiSurat($j);
                    $dasar   = SuratAcara::kunci(SuratJenis::TKA, $isiBaru, (string) $isi['pkey']);
                    $kunci   = $dasar;
                    for ($n = 1; $this->svc->adaKunci($kunci) !== null; $n++) {
                        if ($n > 50) {
                            throw new \DomainException('Surat untuk gelombang itu sudah terlalu banyak.');
                        }
                        $kunci = sha1($dasar . '|' . $n);
                    }
                    $r = $this->svc->buat(SuratJenis::TKA, [
                        'judul' => SuratAcara::judul(SuratJenis::TKA, $isiBaru, (string) $s['perusahaan_nama'], 1), 'tanggal_surat' => date('Y-m-d'),
                        'isi' => $isiBaru + ['pkey' => (string) $isi['pkey'], 'kunci' => $kunci, 'jadwal_id' => (int) $j['id']],
                        'perusahaan_nama' => (string) $s['perusahaan_nama'], 'pengajuan_id' => $s['pengajuan_id'] ?? null, 'siswa' => [$row],
                        'sumber' => SuratSekolah::kolomSumber() ? (string) ($s['sumber'] ?? 'staf') : 'staf',
                    ], $konteks, $p);
                    if (! $r['ok']) {
                        throw new \RuntimeException('surat baru: ' . ($r['pesan'] ?? ''));
                    }
                    $tujuanId = (int) $r['id'];
                    $this->svc->catat($tujuanId, 'pindah', $konteks, $nama . ' dipindah ke surat ini dari ' . $kode . ' (' . $label . ')' . ($catatan !== '' ? ' — ' . $catatan : ''));
                    if ($s['status'] === 'disetujui') { // surat asal sudah disetujui → surat pengganti langsung di-ACC atas nama staf ini
                        (new SuratKeputusan($this->svc, $this->db))->accSetelahBuat([$tujuanId], SuratJenis::TKA, $konteks, $p);
                    }
                }
            }

            // 2) Keluarkan dari surat ini; surat yang jadi kosong dibatalkan (nomor tetap tercatat).
            $kata = $j !== null
                ? $nama . ' dipindah ke ' . SuratJadwal::label($j) . ($tujuanId !== null ? ' (' . SuratSekolahModel::kode($tujuanId) . ')' : '')
                : $nama . ' dikeluarkan dari surat ini';
            $this->svc->catat($suratId, 'pindah', $konteks, $kata . ($catatan !== '' ? ' — ' . $catatan : ''));
            if (count($m['siswa']) === 1) {
                if (! $this->svc->batalOtomatis($suratId, 'Siswa terakhir di surat ini ' . ($j !== null ? 'dipindah ke ' . SuratJadwal::label($j) : 'dikeluarkan') . '.', $konteks, true)) {
                    throw new \DomainException('Surat ' . $kode . ' baru saja berubah. Muat ulang halaman lalu coba lagi.');
                }
            } else {
                if (! $this->svc->aturSiswa($suratId, $siswaId, null, SuratAcara::judul(SuratJenis::TKA, $isi, (string) $s['perusahaan_nama'], count($m['siswa']) - 1))) {
                    throw new \DomainException('Surat ' . $kode . ' baru saja berubah. Muat ulang halaman lalu coba lagi.');
                }
                if ((int) $s['cetak_ke'] > 0) {
                    $ulang[] = $kode;
                }
            }

            // 3) Permintaan siswa ikut pindah: ke surat tujuan, atau (keluarkan saja) ke surat TKA lain yang masih memuatnya.
            $ke = $tujuanId;
            $jd = $j !== null ? (int) $j['id'] : null;
            if ($ke === null) {
                $lain = $this->lainnya($suratId, [$siswaId])[$siswaId][0] ?? null;
                $ke   = $lain['surat_id'] ?? null;
                $jd   = isset($lain['isi']['jadwal_id']) ? (int) $lain['isi']['jadwal_id'] : null;
            }
            if (SuratSekolah::adaPermintaan()) {
                $q = $this->db->table('surat_permintaan')->where('jenis', SuratJenis::TKA)->where('siswa_id', $siswaId)->where('surat_id', $suratId);
                if ($ke === null) {
                    $q->delete();
                } else {
                    $q->update(['surat_id' => $ke, 'jadwal_id' => $jd]);
                    $dobel = array_map('intval', array_column($this->db->table('surat_permintaan')->select('id')->where('jenis', SuratJenis::TKA)
                        ->where('siswa_id', $siswaId)->where('surat_id', $ke)->orderBy('id', 'DESC')->get()->getResultArray(), 'id'));
                    if (count($dobel) > 1) {
                        $this->db->table('surat_permintaan')->whereIn('id', array_slice($dobel, 1))->delete();
                    }
                }
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[Surat] pindah gelombang ' . $kode . ' siswa #' . $siswaId . ' gagal: ' . $e->getMessage());

            return ['ok' => false, 'kode' => 'gagal', 'pesan' => $e instanceof \DomainException ? $e->getMessage() : 'Perubahan gagal disimpan. Tidak ada yang berubah — coba lagi sebentar.'];
        }

        (new AuditModel())->record('update', 'surat_sekolah', $suratId, mb_substr($kode . ' pindah gelombang: ' . $kata . ' — oleh ' . (string) ($konteks['oleh'] ?? 'Staf') . (($konteks['saluran'] ?? 'web') === 'aplikasi' ? ' (via aplikasi)' : ''), 0, 255));
        $ulang = array_values(array_unique($ulang));
        $baru  = $tujuanId !== null ? $this->svc->muat($tujuanId) : null;

        return ['ok' => true, 'kode' => 'ok', 'tujuan_id' => $tujuanId, 'pesan' => $kata . '.'
            . ($baru !== null && $baru['surat']['status'] === 'menunggu' ? ' Surat ' . SuratSekolahModel::kode($tujuanId) . ' menunggu ACC.' : '')
            . (count($m['siswa']) === 1 ? ' Surat ' . $kode . ' kini kosong sehingga dibatalkan.' : '')
            . ($ulang !== [] ? ' Unduh ulang ' . implode(' dan ', $ulang) . ' (perlu cetak ulang, nomor tetap).' : '')];
    }
}
